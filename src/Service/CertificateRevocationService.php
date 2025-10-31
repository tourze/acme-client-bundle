<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

/**
 * 证书撤销服务
 *
 * 负责证书的撤销操作
 */
#[WithMonologChannel(channel: 'acme_client')]
readonly class CertificateRevocationService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 撤销证书
     */
    public function revokeCertificate(Certificate $certificate, int $reason = 0): Certificate
    {
        $account = $this->validateCertificateForRevocation($certificate);
        $privateKey = $this->getPrivateKeyForRevocation($account);
        $payload = $this->buildRevocationPayload($certificate, $reason);

        try {
            $this->performCertificateRevocation($payload, $privateKey, $account);
            $this->updateCertificateAsRevoked($certificate);
            $this->logCertificateRevocation($certificate, $reason);

            return $certificate;
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Certificate revocation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 验证证书可以被撤销
     */
    private function validateCertificateForRevocation(Certificate $certificate): Account
    {
        $order = $certificate->getOrder();
        if (null === $order) {
            throw new AcmeOperationException('Certificate has no associated order');
        }

        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        return $account;
    }

    /**
     * 获取用于撤销的私钥
     *
     * @return \OpenSSLAsymmetricKey
     */
    private function getPrivateKeyForRevocation(Account $account): \OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid account private key');
        }

        return $privateKey;
    }

    /**
     * 构建撤销载荷
     *
     * @return array<string, mixed>
     */
    private function buildRevocationPayload(Certificate $certificate, int $reason): array
    {
        $certificateDer = $this->pemToDer($certificate->getCertificatePem());

        return [
            'certificate' => rtrim(strtr(base64_encode($certificateDer), '+/', '-_'), '='),
            'reason' => $reason,
        ];
    }

    /**
     * 执行证书撤销操作
     *
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey $privateKey
     * @return array<string, mixed>
     */
    private function performCertificateRevocation(array $payload, \OpenSSLAsymmetricKey $privateKey, Account $account): array
    {
        return $this->apiClient->post(
            'revokeCert',
            $payload,
            $privateKey,
            $account->getAccountUrl()
        );
    }

    /**
     * 更新证书为已撤销状态
     */
    private function updateCertificateAsRevoked(Certificate $certificate): void
    {
        $certificate->setStatus(CertificateStatus::REVOKED);
        $certificate->setRevokedTime(new \DateTimeImmutable());

        $this->entityManager->persist($certificate);
        $this->entityManager->flush();
    }

    /**
     * 记录证书撤销日志
     */
    private function logCertificateRevocation(Certificate $certificate, int $reason): void
    {
        $this->logger->info('Certificate revoked successfully', [
            'certificate_id' => $certificate->getId(),
            'serial_number' => $certificate->getSerialNumber(),
            'reason' => $reason,
        ]);
    }

    /**
     * 将PEM格式转换为DER格式
     */
    private function pemToDer(string $pem): string
    {
        $cleanedPem = preg_replace('/-----[^-]+-----/', '', $pem);
        if (null === $cleanedPem) {
            throw new AcmeOperationException('Failed to clean PEM format');
        }

        $der = base64_decode($cleanedPem, true);
        if (false === $der) {
            throw new AcmeOperationException('Failed to convert PEM to DER');
        }

        return $der;
    }
}
