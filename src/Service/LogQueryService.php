<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

#[Autoconfigure(public: true)]
final class LogQueryService
{
    public function __construct(
        private readonly AcmeLogService $logService,
        private readonly AcmeExceptionService $exceptionService,
    ) {
    }

    /**
     * @return array<AcmeOperationLog>
     */
    public function queryOperationLogs(
        ?string $operation,
        ?string $entityType,
        ?int $entityId,
        ?string $level,
        ?\DateTimeImmutable $since,
        int $limit,
    ): array {
        $logs = $this->logService->findLogs($operation, $entityType, $entityId, $level, $limit);

        if (null !== $since) {
            $logs = array_filter($logs, fn ($log) => $log->getOccurredTime() >= $since);
        }

        return $logs;
    }

    /**
     * @return array<AcmeExceptionLog>
     */
    public function queryExceptionLogs(
        ?string $entityType,
        ?int $entityId,
        ?\DateTimeImmutable $since,
        int $limit,
    ): array {
        return $this->exceptionService->findExceptions(null, $entityType, $entityId, $since, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOperationStatistics(\DateTimeImmutable $since): array
    {
        $logs = $this->logService->findLogs(null, null, null, null, 1000);
        $logs = array_filter($logs, fn ($log) => $log->getOccurredTime() >= $since);

        $operationStats = [];
        $levelStats = [];
        $entityStats = [];

        foreach ($logs as $log) {
            $operationStats[$log->getOperation()] = ($operationStats[$log->getOperation()] ?? 0) + 1;
            $levelStats[$log->getLevel()->value] = ($levelStats[$log->getLevel()->value] ?? 0) + 1;
            if (null !== $log->getEntityType()) {
                $entityStats[$log->getEntityType()] = ($entityStats[$log->getEntityType()] ?? 0) + 1;
            }
        }

        return [
            'operations' => $operationStats,
            'levels' => $levelStats,
            'entities' => $entityStats,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function getExceptionStatistics(\DateTimeImmutable $since): array
    {
        return $this->exceptionService->getExceptionStats($since);
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function cleanupLogs(int $days): array
    {
        if ($days < 1) {
            throw new AcmeOperationException('清理天数必须大于0');
        }

        $operationCleaned = $this->logService->cleanupOldLogs($days);
        $exceptionCleaned = $this->exceptionService->cleanupOldExceptions($days);

        return [$operationCleaned, $exceptionCleaned];
    }

    public function parseSinceTime(?string $sinceInput): \DateTimeImmutable
    {
        if (null !== $sinceInput) {
            try {
                return new \DateTimeImmutable($sinceInput);
            } catch (\Throwable) {
                throw new AcmeOperationException("无效的时间格式: {$sinceInput}");
            }
        }

        return new \DateTimeImmutable('-7 days');
    }
}
