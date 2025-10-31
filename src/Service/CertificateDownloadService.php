<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

/**
 * 证书下载服务
 *
 * 负责从ACME服务器下载证书并保存到数据库
 */
#[WithMonologChannel(channel: 'acme_client')]
readonly class CertificateDownloadService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 下载并保存证书
     */
    public function downloadCertificate(Order $order): Certificate
    {
        $this->validateCertificateUrlInOrder($order);

        try {
            $certificate = $this->performCertificateDownload($order);
            $this->saveCertificateToDatabase($certificate);
            $this->logCertificateDownloadSuccess($certificate, $order);

            return $certificate;
        } catch (\Throwable $e) {
            $this->logCertificateDownloadError($order, $e);
            throw new AcmeOperationException("Certificate download failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 验证订单中的证书URL
     */
    private function validateCertificateUrlInOrder(Order $order): void
    {
        if (null === $order->getCertificateUrl()) {
            throw new AcmeOperationException('Certificate URL not available in order');
        }
    }

    /**
     * 执行证书下载
     */
    private function performCertificateDownload(Order $order): Certificate
    {
        $certificateUrl = $order->getCertificateUrl();
        if (null === $certificateUrl) {
            throw new AcmeOperationException('Certificate URL is null');
        }

        $response = $this->apiClient->get($certificateUrl);
        $fullChainPem = $this->extractCertificateFromResponse($response);
        [$leafCertPem, $chainPem] = $this->processCertificateChain($fullChainPem);
        $certInfo = $this->parseCertificateInfo($leafCertPem);

        return $this->createOrUpdateCertificate($order, $leafCertPem, $chainPem, $certInfo);
    }

    /**
     * 保存证书到数据库
     */
    private function saveCertificateToDatabase(Certificate $certificate): void
    {
        $this->entityManager->persist($certificate);
        $this->entityManager->flush();
    }

    /**
     * 从API响应中提取证书PEM内容
     *
     * @param array<string, mixed> $response
     */
    private function extractCertificateFromResponse(array $response): string
    {
        $fullChainPem = $response['certificate'] ?? '';
        if (!is_string($fullChainPem) || '' === $fullChainPem) {
            $fullChainPem = implode("\n", $response);
        }

        if ('' === $fullChainPem) {
            throw new AcmeOperationException('Empty certificate received from server');
        }

        return $fullChainPem;
    }

    /**
     * 处理证书链，分离叶子证书和证书链
     *
     * @return array{0: string, 1: string}
     */
    private function processCertificateChain(string $fullChainPem): array
    {
        $certificates = $this->parseCertificateChain($fullChainPem);
        if ([] === $certificates) {
            throw new AcmeOperationException('No valid certificates found in response');
        }

        $leafCertPem = $certificates[0]; // 第一个是叶子证书
        $chainPem = implode("\n", array_slice($certificates, 1)); // 其余是证书链

        return [$leafCertPem, $chainPem];
    }

    /**
     * 解析证书链
     *
     * @return array<string>
     */
    private function parseCertificateChain(string $fullChainPem): array
    {
        $certificates = [];
        $pattern = '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s';

        if (preg_match_all($pattern, $fullChainPem, $matches) > 0) {
            $certificates = $matches[0];
        }

        return $certificates;
    }

    /**
     * 解析证书信息
     *
     * @return array<string, mixed>
     */
    private function parseCertificateInfo(string $certificatePem): array
    {
        $certInfo = openssl_x509_parse($certificatePem);
        if (false === $certInfo) {
            throw new AcmeOperationException('Failed to parse certificate');
        }

        /** @var array<string, mixed> $certInfo */
        return $certInfo;
    }

    /**
     * 创建或更新证书实体
     *
     * @param array<string, mixed> $certInfo
     */
    private function createOrUpdateCertificate(Order $order, string $leafCertPem, string $chainPem, array $certInfo): Certificate
    {
        $certificate = $order->getCertificate();
        if (null === $certificate) {
            $certificate = new Certificate();
            $certificate->setOrder($order);
        }

        $certificate->setCertificatePem($leafCertPem);
        $certificate->setCertificateChainPem($chainPem);
        $certificate->setStatus(CertificateStatus::ISSUED);

        $this->populateCertificateInfo($certificate, $certInfo);

        return $certificate;
    }

    /**
     * 填充证书信息
     *
     * @param array<string, mixed> $certInfo
     */
    private function populateCertificateInfo(Certificate $certificate, array $certInfo): void
    {
        $this->setCertificateBasicInfo($certificate, $certInfo);
        $this->setCertificateValidityPeriod($certificate, $certInfo);
        $this->setCertificateIssuer($certificate, $certInfo);
        $this->setCertificateDomains($certificate, $certInfo);
        $this->setCertificateFingerprint($certificate);
    }

    /**
     * @param array<string, mixed> $certInfo
     */
    private function setCertificateBasicInfo(Certificate $certificate, array $certInfo): void
    {
        if (isset($certInfo['serialNumber']) && is_string($certInfo['serialNumber'])) {
            $certificate->setSerialNumber($certInfo['serialNumber']);
        }
    }

    /**
     * @param array<string, mixed> $certInfo
     */
    private function setCertificateValidityPeriod(Certificate $certificate, array $certInfo): void
    {
        if (isset($certInfo['validFrom_time_t']) && (is_int($certInfo['validFrom_time_t']) || is_string($certInfo['validFrom_time_t']))) {
            $certificate->setNotBeforeTime(new \DateTimeImmutable('@' . $certInfo['validFrom_time_t']));
        }
        if (isset($certInfo['validTo_time_t']) && (is_int($certInfo['validTo_time_t']) || is_string($certInfo['validTo_time_t']))) {
            $certificate->setNotAfterTime(new \DateTimeImmutable('@' . $certInfo['validTo_time_t']));
        }
    }

    /**
     * @param array<string, mixed> $certInfo
     */
    private function setCertificateIssuer(Certificate $certificate, array $certInfo): void
    {
        if (isset($certInfo['issuer']) && is_array($certInfo['issuer'])) {
            $issuer = $certInfo['issuer'];
            if (isset($issuer['CN']) && is_string($issuer['CN'])) {
                $certificate->setIssuer($issuer['CN']);
            }
        }
    }

    /**
     * @param array<string, mixed> $certInfo
     */
    private function setCertificateDomains(Certificate $certificate, array $certInfo): void
    {
        $domains = $this->extractDomainsFromCertInfo($certInfo);
        $certificate->setDomains(array_unique($domains));
    }

    /**
     * 从证书信息中提取域名
     *
     * @param array<string, mixed> $certInfo
     * @return array<string>
     */
    private function extractDomainsFromCertInfo(array $certInfo): array
    {
        $domains = [];

        if (isset($certInfo['subject']) && is_array($certInfo['subject'])) {
            $subject = $certInfo['subject'];
            if (isset($subject['CN']) && is_string($subject['CN'])) {
                $domains[] = $subject['CN'];
            }
        }

        return array_merge($domains, $this->extractSanDomains($certInfo));
    }

    /**
     * 提取SAN（Subject Alternative Name）中的域名
     *
     * @param array<string, mixed> $certInfo
     * @return array<string>
     */
    private function extractSanDomains(array $certInfo): array
    {
        if (!isset($certInfo['extensions']) || !is_array($certInfo['extensions'])) {
            return [];
        }

        $extensions = $certInfo['extensions'];
        if (!isset($extensions['subjectAltName']) || !is_string($extensions['subjectAltName'])) {
            return [];
        }

        $sanString = $extensions['subjectAltName'];
        if (preg_match_all('/DNS:([^,\s]+)/', $sanString, $matches) > 0) {
            return $matches[1];
        }

        return [];
    }

    private function setCertificateFingerprint(Certificate $certificate): void
    {
        $fingerprint = openssl_x509_fingerprint($certificate->getCertificatePem(), 'sha256');
        if (false !== $fingerprint) {
            $certificate->setFingerprint($fingerprint);
        }
    }

    /**
     * 记录证书下载成功日志
     */
    private function logCertificateDownloadSuccess(Certificate $certificate, Order $order): void
    {
        $this->logger->info('Certificate downloaded and saved successfully', [
            'certificate_id' => $certificate->getId(),
            'order_id' => $order->getId(),
            'serial_number' => $certificate->getSerialNumber(),
            'domains' => $certificate->getDomains(),
        ]);
    }

    /**
     * 记录证书下载失败日志
     */
    private function logCertificateDownloadError(Order $order, \Throwable $e): void
    {
        $this->logger->error('Failed to download certificate', [
            'order_id' => $order->getId(),
            'certificate_url' => $order->getCertificateUrl(),
            'error' => $e->getMessage(),
        ]);
    }
}
