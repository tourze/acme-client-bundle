<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;

/**
 * 证书查询服务
 *
 * 负责证书的各种查询操作
 */
readonly class CertificateQueryService
{
    public function __construct(
        private CertificateRepository $certificateRepository,
    ) {
    }

    /**
     * 查找即将过期的证书
     *
     * @return array<Certificate>
     */
    public function findExpiringCertificates(int $days = 30): array
    {
        $threshold = new \DateTimeImmutable("+{$days} days");

        /** @var array<Certificate> */
        return $this->certificateRepository->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->andWhere('c.notAfterTime <= :threshold')
            ->setParameter('statuses', [CertificateStatus::VALID, CertificateStatus::ISSUED])
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找特定域名的证书
     *
     * @return array<Certificate>
     */
    public function findCertificatesByDomain(string $domain): array
    {
        $allCertificates = $this->certificateRepository->findAll();
        $matchingCertificates = $this->filterCertificatesByDomain($allCertificates, $domain);

        return $this->sortCertificatesByExpirationDesc($matchingCertificates);
    }

    /**
     * 查找所有有效的证书
     *
     * @return array<Certificate>
     */
    public function findValidCertificates(): array
    {
        /** @var array<Certificate> */
        return $this->certificateRepository->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->setParameter('statuses', [CertificateStatus::VALID, CertificateStatus::ISSUED])
            ->orderBy('c.notAfterTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 按状态查找证书
     *
     * @return array<Certificate>
     */
    public function findCertificatesByStatus(CertificateStatus $status): array
    {
        return $this->certificateRepository->findBy(
            ['status' => $status],
            ['id' => 'DESC']
        );
    }

    /**
     * 按订单查找证书
     *
     * @return array<Certificate>
     */
    public function findCertificatesByOrder(Order $order): array
    {
        return $this->certificateRepository->findBy(
            ['order' => $order],
            ['id' => 'DESC']
        );
    }

    /**
     * 按域名过滤证书
     *
     * @param array<Certificate> $certificates
     * @return array<Certificate>
     */
    private function filterCertificatesByDomain(array $certificates, string $domain): array
    {
        $matchingCertificates = [];
        foreach ($certificates as $certificate) {
            if ($this->certificateContainsDomain($certificate, $domain)) {
                $matchingCertificates[] = $certificate;
            }
        }

        return $matchingCertificates;
    }

    /**
     * 检查证书是否包含指定域名
     */
    private function certificateContainsDomain(Certificate $certificate, string $domain): bool
    {
        $domains = $certificate->getDomains();

        return [] !== $domains && in_array($domain, $domains, true);
    }

    /**
     * 按过期时间降序排序证书
     *
     * @param array<Certificate> $certificates
     * @return array<Certificate>
     */
    private function sortCertificatesByExpirationDesc(array $certificates): array
    {
        usort($certificates, function (Certificate $a, Certificate $b): int {
            return $this->compareCertificatesByExpiration($a, $b);
        });

        return $certificates;
    }

    /**
     * 比较两个证书的过期时间
     */
    private function compareCertificatesByExpiration(Certificate $a, Certificate $b): int
    {
        $aTime = $a->getNotAfterTime();
        $bTime = $b->getNotAfterTime();

        if (null === $aTime && null === $bTime) {
            return 0;
        }
        if (null === $aTime) {
            return 1;
        }
        if (null === $bTime) {
            return -1;
        }

        return $bTime <=> $aTime;
    }
}
