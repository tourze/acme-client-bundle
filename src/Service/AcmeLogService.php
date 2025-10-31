<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Repository\AcmeOperationLogRepository;

/**
 * ACME 日志服务
 *
 * 负责记录所有 ACME 操作的详细日志
 */
#[Autoconfigure(public: true)]
readonly class AcmeLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AcmeOperationLogRepository $operationLogRepository,
        private AcmeExceptionService $exceptionService,
    ) {
    }

    /**
     * 记录账户操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public function logAccountOperation(
        string $operation,
        string $message,
        ?int $accountId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO,
    ): AcmeOperationLog {
        $log = AcmeOperationLog::accountOperation($operation, $message, $accountId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录订单操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public function logOrderOperation(
        string $operation,
        string $message,
        ?int $orderId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO,
    ): AcmeOperationLog {
        $log = AcmeOperationLog::orderOperation($operation, $message, $orderId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录质询操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public function logChallengeOperation(
        string $operation,
        string $message,
        ?int $challengeId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO,
    ): AcmeOperationLog {
        $log = AcmeOperationLog::challengeOperation($operation, $message, $challengeId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录证书操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public function logCertificateOperation(
        string $operation,
        string $message,
        ?int $certificateId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO,
    ): AcmeOperationLog {
        $log = AcmeOperationLog::certificateOperation($operation, $message, $certificateId, $details);
        $log->setLevel($level);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * 记录通用操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public function logOperation(
        string $operation,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
        LogLevel $level = LogLevel::INFO,
    ): AcmeOperationLog {
        $log = new AcmeOperationLog();
        $log->setOperation($operation);
        $log->setMessage($message);
        $log->setLevel($level);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setContext($details);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
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
    ): void {
        $this->exceptionService->logException($exception, $entityType, $entityId, $context);
    }

    /**
     * 查询操作日志
     *
     * @return AcmeOperationLog[]
     */
    public function findLogs(
        ?string $operation = null,
        ?string $entityType = null,
        ?int $entityId = null,
        string|LogLevel|null $level = null,
        int $limit = 100,
    ): array {
        $qb = $this->operationLogRepository
            ->createQueryBuilder('l')
            ->orderBy('l.occurredTime', 'DESC')
            ->setMaxResults($limit)
        ;

        if (null !== $operation) {
            $qb->andWhere('l.operation = :operation')
                ->setParameter('operation', $operation)
            ;
        }

        if (null !== $entityType) {
            $qb->andWhere('l.entityType = :entityType')
                ->setParameter('entityType', $entityType)
            ;
        }

        if (null !== $entityId) {
            $qb->andWhere('l.entityId = :entityId')
                ->setParameter('entityId', $entityId)
            ;
        }

        if (null !== $level) {
            if (is_string($level)) {
                $level = LogLevel::from($level);
            }
            $qb->andWhere('l.level = :level')
                ->setParameter('level', $level)
            ;
        }

        /** @var array<AcmeOperationLog> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 清理旧日志
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        $qb = $this->operationLogRepository->createQueryBuilder('l');
        $query = $qb->delete()
            ->where('l.occurredTime < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
        ;

        $result = $query->execute();

        return is_int($result) ? $result : 0;
    }
}
