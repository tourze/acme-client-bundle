<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Repository\AcmeExceptionLogRepository;

/**
 * ACME 异常服务
 *
 * 负责记录和管理所有 ACME 操作过程中的异常
 */
class AcmeExceptionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AcmeExceptionLogRepository $exceptionLogRepository,
    ) {}

    /**
     * 记录异常日志
     */
    public function logException(
        \Throwable $exception,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $context = null
    ): AcmeExceptionLog {
        $log = AcmeExceptionLog::fromException($exception, $entityType, $entityId, $context);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 查询异常日志
     */
    public function findExceptions(
        ?string $exceptionClass = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?\DateTimeImmutable $since = null,
        int $limit = 100
    ): array {
        $qb = $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults($limit);

        if ($exceptionClass !== null) {
            $qb->andWhere('e.exceptionClass = :exceptionClass')
                ->setParameter('exceptionClass', $exceptionClass);
        }

        if ($entityType !== null) {
            $qb->andWhere('e.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($entityId !== null) {
            $qb->andWhere('e.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        }

        if ($since !== null) {
            $qb->andWhere('e.occurredAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取异常统计信息
     */
    public function getExceptionStats(?\DateTimeImmutable $since = null): array
    {
        $qb = $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->select('e.exceptionClass, COUNT(e.id) as count')
            ->groupBy('e.exceptionClass')
            ->orderBy('count', 'DESC');

        if ($since !== null) {
            $qb->andWhere('e.occurredAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 清理旧异常日志
     */
    public function cleanupOldExceptions(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        $qb = $this->entityManager->createQueryBuilder();
        $query = $qb->delete(AcmeExceptionLog::class, 'e')
            ->where('e.occurredAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery();

        return $query->execute();
    }

    /**
     * 检查是否存在重复异常（同样的异常在短时间内重复出现）
     */
    public function hasDuplicateException(
        string $exceptionClass,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        int $minutesWindow = 5
    ): bool {
        $since = new \DateTimeImmutable("-{$minutesWindow} minutes");

        $qb = $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->where('e.exceptionClass = :exceptionClass')
            ->andWhere('e.message = :message')
            ->andWhere('e.occurredAt >= :since')
            ->setParameter('exceptionClass', $exceptionClass)
            ->setParameter('message', $message)
            ->setParameter('since', $since);

        if ($entityType !== null) {
            $qb->andWhere('e.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($entityId !== null) {
            $qb->andWhere('e.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * 获取最近的异常日志
     */
    public function getRecentExceptions(int $hours = 24, int $limit = 50): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return $this->exceptionLogRepository
            ->createQueryBuilder('e')
            ->where('e.occurredAt >= :since')
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }
}
