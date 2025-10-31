<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;

/**
 * ACME 证书服务（Facade）
 *
 * 负责协调各个专门的证书服务，为外部提供统一接口
 *
 * @phpstan-type CertInfo array<string, mixed>
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'acme_client')]
readonly class CertificateService
{
    public function __construct(
        private CertificateDownloadService $downloadService,
        private CertificateRevocationService $revocationService,
        private CertificateValidationService $validationService,
        private CertificateQueryService $queryService,
        private CsrGeneratorService $csrGeneratorService,
    ) {
    }

    /**
     * 下载并保存证书
     */
    public function downloadCertificate(Order $order): Certificate
    {
        return $this->downloadService->downloadCertificate($order);
    }

    /**
     * 撤销证书
     */
    public function revokeCertificate(Certificate $certificate, int $reason = 0): Certificate
    {
        return $this->revocationService->revokeCertificate($certificate, $reason);
    }

    /**
     * 验证证书是否有效
     */
    public function validateCertificate(Certificate $certificate): bool
    {
        return $this->validationService->validateCertificate($certificate);
    }

    /**
     * 检查证书是否有效（别名方法）
     */
    public function isCertificateValid(Certificate $certificate): bool
    {
        return $this->validateCertificate($certificate);
    }

    /**
     * 生成证书签名请求(CSR)
     *
     * @param array<string> $domains
     * @param array<string, string> $extraInfo
     */
    public function generateCsr(array $domains, string $privateKeyPem, array $extraInfo = []): string
    {
        return $this->csrGeneratorService->generateCsr($domains, $privateKeyPem, $extraInfo);
    }

    /**
     * 查找即将过期的证书
     *
     * @return array<Certificate>
     */
    public function findExpiringCertificates(int $days = 30): array
    {
        return $this->queryService->findExpiringCertificates($days);
    }

    /**
     * 查找特定域名的证书
     *
     * @return array<Certificate>
     */
    public function findCertificatesByDomain(string $domain): array
    {
        return $this->queryService->findCertificatesByDomain($domain);
    }

    /**
     * 查找所有有效的证书
     *
     * @return array<Certificate>
     */
    public function findValidCertificates(): array
    {
        return $this->queryService->findValidCertificates();
    }

    /**
     * 按状态查找证书
     *
     * @return array<Certificate>
     */
    public function findCertificatesByStatus(CertificateStatus $status): array
    {
        return $this->queryService->findCertificatesByStatus($status);
    }

    /**
     * 按订单查找证书
     *
     * @return array<Certificate>
     */
    public function findCertificatesByOrder(Order $order): array
    {
        return $this->queryService->findCertificatesByOrder($order);
    }
}
