<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Repository\AcmeExceptionLogRepository;

/**
 * ACME 异常服务
 *
 * 负责记录和管理所有 ACME 操作过程中的异常
 */
#[Autoconfigure(public: true)]
readonly class AcmeExceptionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AcmeExceptionLogRepository $exceptionLogRepository,
    ) {
    }

    /**
     * 记录异常日志
     *
     * @param array<string, mixed>|null $context
     */
    public function logException(
        \Throwable $exception,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $context = null,
    ): AcmeExceptionLog {
        $log = AcmeExceptionLog::fromException($exception, $entityType, $entityId, $context);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 查询异常日志
     *
     * @return AcmeExceptionLog[]
     */
    public function findExceptions(
        ?string $exceptionClass = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?\DateTimeImmutable $since = null,
        int $limit = 100,
    ): array {
        $qb = $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->orderBy('e.occurredTime', 'DESC')
            ->setMaxResults($limit)
        ;

        if (null !== $exceptionClass) {
            $qb->andWhere('e.exceptionClass = :exceptionClass')
                ->setParameter('exceptionClass', $exceptionClass)
            ;
        }

        if (null !== $entityType) {
            $qb->andWhere('e.entityType = :entityType')
                ->setParameter('entityType', $entityType)
            ;
        }

        if (null !== $entityId) {
            $qb->andWhere('e.entityId = :entityId')
                ->setParameter('entityId', $entityId)
            ;
        }

        if (null !== $since) {
            $qb->andWhere('e.occurredTime >= :since')
                ->setParameter('since', $since)
            ;
        }

        /** @var array<AcmeExceptionLog> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 查询异常日志（别名方法，向后兼容）
     *
     * @return AcmeExceptionLog[]
     */
    public function findExceptionLogs(
        ?string $exceptionClass = null,
        ?string $entityType = null,
        ?int $entityId = null,
        int $limit = 100,
    ): array {
        return $this->findExceptions($exceptionClass, $entityType, $entityId, null, $limit);
    }

    /**
     * 获取异常统计信息
     *
     * @return array<int, array{exceptionClass: string, count: int}>
     */
    public function getExceptionStats(?\DateTimeImmutable $since = null): array
    {
        $qb = $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->select('e.exceptionClass, COUNT(e.id) as count')
            ->groupBy('e.exceptionClass')
            ->orderBy('count', 'DESC')
        ;

        if (null !== $since) {
            $qb->andWhere('e.occurredTime >= :since')
                ->setParameter('since', $since)
            ;
        }

        /** @var array<int, array{exceptionClass: string, count: int}> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 清理旧异常日志
     */
    public function cleanupOldExceptions(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        $qb = $this->exceptionLogRepository->createQueryBuilder('e');
        $query = $qb->delete()
            ->where('e.occurredTime < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
        ;

        $result = $query->execute();

        return is_int($result) ? $result : 0;
    }

    /**
     * 检查是否存在重复异常（同样的异常在短时间内重复出现）
     */
    public function hasDuplicateException(
        string $exceptionClass,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        int $minutesWindow = 5,
    ): bool {
        $since = new \DateTimeImmutable("-{$minutesWindow} minutes");

        $qb = $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->where('e.exceptionClass = :exceptionClass')
            ->andWhere('e.message = :message')
            ->andWhere('e.occurredTime >= :since')
            ->setParameter('exceptionClass', $exceptionClass)
            ->setParameter('message', $message)
            ->setParameter('since', $since)
        ;

        if (null !== $entityType) {
            $qb->andWhere('e.entityType = :entityType')
                ->setParameter('entityType', $entityType)
            ;
        }

        if (null !== $entityId) {
            $qb->andWhere('e.entityId = :entityId')
                ->setParameter('entityId', $entityId)
            ;
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * 获取最近的异常日志
     *
     * @return AcmeExceptionLog[]
     */
    public function getRecentExceptions(int $hours = 24, int $limit = 50): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        /** @var array<AcmeExceptionLog> */
        return $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->where('e.occurredTime >= :since')
            ->orderBy('e.occurredTime', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }
}
