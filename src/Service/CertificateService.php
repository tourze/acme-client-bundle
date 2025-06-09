<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;

/**
 * ACME 证书服务
 *
 * 负责证书的下载、解析、验证、撤销和生命周期管理
 */
class CertificateService
{
    public function __construct(
        private readonly AcmeApiClient $apiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateRepository $certificateRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 下载并保存证书
     */
    public function downloadCertificate(Order $order): Certificate
    {
        if (!$order->getCertificateUrl()) {
            throw new AcmeClientException('Certificate URL not available in order');
        }

        try {
            // 从ACME服务器下载证书
            $response = $this->apiClient->get($order->getCertificateUrl());

            // 假设响应直接包含PEM格式的证书链
            $fullChainPem = $response['certificate'] ?? '';
            if (empty($fullChainPem)) {
                // 如果响应是数组格式，可能需要不同的处理
                $fullChainPem = implode("\n", $response);
            }

            if (empty($fullChainPem)) {
                throw new AcmeClientException('Empty certificate received from server');
            }

            // 分离证书和证书链
            $certificates = $this->parseCertificateChain($fullChainPem);
            if (empty($certificates)) {
                throw new AcmeClientException('No valid certificates found in response');
            }

            $leafCertPem = $certificates[0]; // 第一个是叶子证书
            $chainPem = implode("\n", array_slice($certificates, 1)); // 其余是证书链

            // 解析证书信息
            $certInfo = openssl_x509_parse($leafCertPem);
            if (!$certInfo) {
                throw new AcmeClientException('Failed to parse certificate');
            }

            // 创建或更新证书实体
            $certificate = $order->getCertificate();
            if (!$certificate) {
                $certificate = new Certificate();
                $certificate->setOrder($order);
            }

            $certificate->setCertificatePem($leafCertPem);
            $certificate->setCertificateChainPem($chainPem);
            $certificate->setStatus(CertificateStatus::VALID);

            // 设置证书信息
            $this->populateCertificateInfo($certificate, $certInfo);

            $this->entityManager->persist($certificate);
            $this->entityManager->flush();

            $this->logger->info('Certificate downloaded and saved successfully', [
                'certificate_id' => $certificate->getId(),
                'order_id' => $order->getId(),
                'serial_number' => $certificate->getSerialNumber(),
                'domains' => $certificate->getDomains(),
            ]);

            return $certificate;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to download certificate', [
                'order_id' => $order->getId(),
                'certificate_url' => $order->getCertificateUrl(),
                'error' => $e->getMessage(),
            ]);

            throw new AcmeClientException(
                "Certificate download failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 撤销证书
     */
    public function revokeCertificate(Certificate $certificate, int $reason = 0): Certificate
    {
        $order = $certificate->getOrder();
        $account = $order->getAccount();
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (!$privateKey) {
            throw new AcmeClientException('Invalid account private key');
        }

        // 准备撤销载荷
        $certificateDer = $this->pemToDer($certificate->getCertificatePem());
        $payload = [
            'certificate' => rtrim(strtr(base64_encode($certificateDer), '+/', '-_'), '='),
            'reason' => $reason,
        ];

        try {
            $response = $this->apiClient->post(
                'revokeCert',
                $payload,
                $privateKey,
                $account->getAccountUrl()
            );

            // 更新证书状态
            $certificate->setStatus(CertificateStatus::REVOKED);
            $certificate->setRevokedTime(new \DateTimeImmutable());

            $this->entityManager->persist($certificate);
            $this->entityManager->flush();

            $this->logger->info('Certificate revoked successfully', [
                'certificate_id' => $certificate->getId(),
                'serial_number' => $certificate->getSerialNumber(),
                'reason' => $reason,
            ]);

            return $certificate;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Certificate revocation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 验证证书是否有效
     */
    public function validateCertificate(Certificate $certificate): bool
    {
        try {
            $x509 = openssl_x509_read($certificate->getCertificatePem());
            if (!$x509) {
                return false;
            }

            // 检查证书是否过期
            if ($certificate->isExpired()) {
                return false;
            }

            // 检查证书状态
            if ($certificate->getStatus() !== CertificateStatus::VALID) {
                return false;
            }

            // 验证证书链（如果有）
            if ($certificate->getCertificateChainPem()) {
                return $this->validateCertificateChain($certificate);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Certificate validation failed', [
                'certificate_id' => $certificate->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 生成证书签名请求(CSR)
     */
    public function generateCsr(array $domains, string $privateKeyPem, array $extraInfo = []): string
    {
        $dn = [
            'CN' => $domains[0], // 第一个域名作为通用名称
        ];

        // 合并额外信息
        $dn = array_merge($dn, $extraInfo);

        // 准备SAN扩展
        $config = [
            'digest_alg' => 'sha256',
            'req_extensions' => 'v3_req',
            'x509_extensions' => 'v3_ca',
        ];

        // 构建SAN字符串
        $sanString = 'DNS:' . implode(',DNS:', $domains);

        // 创建配置文件内容
        $configText = "
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req

[req_distinguished_name]

[v3_req]
subjectAltName = {$sanString}
";

        // 写入临时配置文件
        $configFile = tempnam(sys_get_temp_dir(), 'openssl_');
        file_put_contents($configFile, $configText);
        $config['config'] = $configFile;

        try {
            $privateKey = openssl_pkey_get_private($privateKeyPem);
            if (!$privateKey) {
                throw new AcmeClientException('Invalid private key for CSR generation');
            }

            $csr = openssl_csr_new($dn, $privateKey, $config);
            if (!$csr) {
                throw new AcmeClientException('Failed to generate CSR');
            }

            $csrPem = '';
            if (!openssl_csr_export($csr, $csrPem)) {
                throw new AcmeClientException('Failed to export CSR');
            }

            return $csrPem;
        } finally {
            // 清理临时文件
            if (file_exists($configFile)) {
                unlink($configFile);
            }
        }
    }

    /**
     * 查找即将过期的证书
     */
    public function findExpiringCertificates(int $days = 30): array
    {
        $threshold = new \DateTimeImmutable("+{$days} days");

        return $this->certificateRepository->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.notAfterTime <= :threshold')
            ->setParameter('status', CertificateStatus::VALID)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找特定域名的证书
     */
    public function findCertificatesByDomain(string $domain): array
    {
        return $this->certificateRepository->createQueryBuilder('c')
            ->where('JSON_CONTAINS(c.domains, :domain) = 1')
            ->setParameter('domain', json_encode($domain))
            ->orderBy('c.notAfterTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找所有有效的证书
     */
    public function findValidCertificates(): array
    {
        return $this->certificateRepository->findBy(
            ['status' => CertificateStatus::VALID],
            ['notAfterTime' => 'ASC']
        );
    }

    /**
     * 解析证书链
     */
    private function parseCertificateChain(string $fullChainPem): array
    {
        $certificates = [];
        $pattern = '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s';

        if (preg_match_all($pattern, $fullChainPem, $matches)) {
            $certificates = $matches[0];
        }

        return $certificates;
    }

    /**
     * 填充证书信息
     */
    private function populateCertificateInfo(Certificate $certificate, array $certInfo): void
    {
        // 设置序列号
        if (isset($certInfo['serialNumber'])) {
            $certificate->setSerialNumber($certInfo['serialNumber']);
        }

        // 设置有效期
        if (isset($certInfo['validFrom_time_t'])) {
            $certificate->setNotBeforeTime(new \DateTimeImmutable('@' . $certInfo['validFrom_time_t']));
        }
        if (isset($certInfo['validTo_time_t'])) {
            $certificate->setNotAfterTime(new \DateTimeImmutable('@' . $certInfo['validTo_time_t']));
        }

        // 设置颁发者
        if (isset($certInfo['issuer']['CN'])) {
            $certificate->setIssuer($certInfo['issuer']['CN']);
        }

        // 提取域名
        $domains = [];

        // 主域名
        if (isset($certInfo['subject']['CN'])) {
            $domains[] = $certInfo['subject']['CN'];
        }

        // SAN域名
        if (isset($certInfo['extensions']['subjectAltName'])) {
            $sanString = $certInfo['extensions']['subjectAltName'];
            if (preg_match_all('/DNS:([^,\s]+)/', $sanString, $matches)) {
                $domains = array_merge($domains, $matches[1]);
            }
        }

        $certificate->setDomains(array_unique($domains));

        // 生成指纹
        $fingerprint = openssl_x509_fingerprint($certificate->getCertificatePem(), 'sha256');
        if ($fingerprint) {
            $certificate->setFingerprint($fingerprint);
        }
    }

    /**
     * 验证证书链
     */
    private function validateCertificateChain(Certificate $certificate): bool
    {
        // 这里可以实现证书链验证逻辑
        // 例如验证签名、检查CA证书等
        return true;
    }

    /**
     * 将PEM格式转换为DER格式
     */
    private function pemToDer(string $pem): string
    {
        $der = base64_decode(preg_replace('/-----[^-]+-----/', '', $pem));
        if ($der === false) {
            throw new AcmeClientException('Failed to convert PEM to DER');
        }
        return $der;
    }
}
