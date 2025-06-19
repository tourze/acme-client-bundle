<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;

/**
 * ACME 日志服务
 *
 * 负责记录所有 ACME 操作的详细日志
 */
class AcmeLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * 记录账户操作日志
     */
    public function logAccountOperation(
        string $operation,
        string $message,
        ?int $accountId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO
    ): AcmeOperationLog {
        $log = AcmeOperationLog::accountOperation($operation, $message, $accountId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录订单操作日志
     */
    public function logOrderOperation(
        string $operation,
        string $message,
        ?int $orderId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO
    ): AcmeOperationLog {
        $log = AcmeOperationLog::orderOperation($operation, $message, $orderId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录质询操作日志
     */
    public function logChallengeOperation(
        string $operation,
        string $message,
        ?int $challengeId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO
    ): AcmeOperationLog {
        $log = AcmeOperationLog::challengeOperation($operation, $message, $challengeId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录证书操作日志
     */
    public function logCertificateOperation(
        string $operation,
        string $message,
        ?int $certificateId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO
    ): AcmeOperationLog {
        $log = AcmeOperationLog::certificateOperation($operation, $message, $certificateId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录通用操作日志
     */
    public function logOperation(
        string $operation,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO
    ): AcmeOperationLog {
        $log = new AcmeOperationLog();
        $log
            ->setOperation($operation)
            ->setMessage($message)
            ->setLevel($level)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setContext($details);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录异常日志
     */
    public function logException(
        \Throwable $exception,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $context = null
    ): void {
        $exceptionService = new AcmeExceptionService($this->entityManager);
        $exceptionService->logException($exception, $entityType, $entityId, $context);
    }

    /**
     * 查询操作日志
     */
    public function findLogs(
        ?string $operation = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $level = null,
        int $limit = 100
    ): array {
        $qb = $this->entityManager->getRepository(AcmeOperationLog::class)
            ->createQueryBuilder('l')
            ->orderBy('l.occurredTime', 'DESC')
            ->setMaxResults($limit);

        if ($operation !== null) {
            $qb->andWhere('l.operation = :operation')
                ->setParameter('operation', $operation);
        }

        if ($entityType !== null) {
            $qb->andWhere('l.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($entityId !== null) {
            $qb->andWhere('l.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        }

        if ($level !== null) {
            $qb->andWhere('l.level = :level')
                ->setParameter('level', $level);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 清理旧日志
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        $qb = $this->entityManager->createQueryBuilder();
        $query = $qb->delete(AcmeOperationLog::class, 'l')
            ->where('l.occurredTime < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery();

        return $query->execute();
    }
}
