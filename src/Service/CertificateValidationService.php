<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;

/**
 * 证书验证服务
 *
 * 负责证书的有效性验证
 */
#[WithMonologChannel(channel: 'acme_client')]
readonly class CertificateValidationService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 验证证书是否有效
     */
    public function validateCertificate(Certificate $certificate): bool
    {
        try {
            return $this->performCertificateValidation($certificate);
        } catch (\Throwable $e) {
            $this->logCertificateValidationError($certificate, $e);

            return false;
        }
    }

    /**
     * 执行证书验证检查
     */
    private function performCertificateValidation(Certificate $certificate): bool
    {
        if (!$this->canReadCertificate($certificate)) {
            return false;
        }

        if (!$this->isCertificateNotExpired($certificate)) {
            return false;
        }

        if (!$this->hasCertificateValidStatus($certificate)) {
            return false;
        }

        return $this->validateChainIfPresent($certificate);
    }

    /**
     * 检查证书是否可读
     */
    private function canReadCertificate(Certificate $certificate): bool
    {
        $x509 = openssl_x509_read($certificate->getCertificatePem());

        return false !== $x509;
    }

    /**
     * 检查证书是否未过期
     */
    private function isCertificateNotExpired(Certificate $certificate): bool
    {
        return !$certificate->isExpired();
    }

    /**
     * 检查证书状态是否有效
     */
    private function hasCertificateValidStatus(Certificate $certificate): bool
    {
        return in_array($certificate->getStatus(), [CertificateStatus::VALID, CertificateStatus::ISSUED], true);
    }

    /**
     * 如果存在证书链则验证
     */
    private function validateChainIfPresent(Certificate $certificate): bool
    {
        if (null !== $certificate->getCertificateChainPem()) {
            return $this->validateCertificateChain($certificate);
        }

        return true;
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
     * 记录证书验证错误日志
     */
    private function logCertificateValidationError(Certificate $certificate, \Throwable $e): void
    {
        $this->logger->error('Certificate validation failed', [
            'certificate_id' => $certificate->getId(),
            'error' => $e->getMessage(),
        ]);
    }
}
