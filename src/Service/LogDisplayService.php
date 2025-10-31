<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;

#[Autoconfigure(public: true)]
final class LogDisplayService
{
    /**
     * @param array<AcmeOperationLog> $logs
     */
    public function displayOperationLogsResults(
        SymfonyStyle $io,
        array $logs,
        ?string $operation = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $level = null,
        ?\DateTimeImmutable $since = null,
    ): void {
        if (0 === count($logs)) {
            $io->info('没有找到匹配的操作日志');

            return;
        }

        $io->info('找到 ' . count($logs) . ' 条操作日志');

        $this->displayQueryConditions($io, $operation, $entityType, $entityId, $level, $since);
        $this->displayOperationLogsTable($io, $logs);
        $this->showOperationLogDetails($io, $logs);
    }

    /**
     * @param array<AcmeExceptionLog> $exceptions
     */
    public function displayExceptionLogsResults(
        SymfonyStyle $io,
        array $exceptions,
        ?string $entityType = null,
        ?int $entityId = null,
        ?\DateTimeImmutable $since = null,
    ): void {
        if (0 === count($exceptions)) {
            $io->info('没有找到匹配的异常日志');

            return;
        }

        $io->info('找到 ' . count($exceptions) . ' 条异常日志');

        $this->displayExceptionQueryConditions($io, $entityType, $entityId, $since);
        $this->displayExceptionLogsTable($io, $exceptions);
        $this->showExceptionLogDetails($io, $exceptions);
    }

    /**
     * @param array<string, mixed> $statistics
     */
    public function displayOperationStatistics(SymfonyStyle $io, array $statistics, \DateTimeImmutable $since): void
    {
        $io->section('ACME 日志统计信息');
        $io->text('统计时间范围: ' . $since->format('Y-m-d H:i:s') . ' 至今');

        $operations = $statistics['operations'] ?? [];
        if (!is_array($operations) || 0 === count($operations)) {
            $io->info('该时间范围内没有操作记录');

            return;
        }

        /** @var array<string, int> $operationStats */
        $operationStats = $operations;
        $this->displayOperationTypeStats($io, $operationStats);

        $levels = $statistics['levels'] ?? [];
        if (is_array($levels)) {
            /** @var array<string, int> $levelStats */
            $levelStats = $levels;
            $this->displayLevelStats($io, $levelStats);
        }

        $entities = $statistics['entities'] ?? [];
        if (is_array($entities) && count($entities) > 0) {
            /** @var array<string, int> $entityStats */
            $entityStats = $entities;
            $this->displayEntityStats($io, $entityStats);
        }
    }

    /**
     * @param array<mixed> $stats
     */
    public function displayExceptionStatistics(SymfonyStyle $io, array $stats, \DateTimeImmutable $since): void
    {
        $io->section('ACME 日志统计信息');
        $io->text('统计时间范围: ' . $since->format('Y-m-d H:i:s') . ' 至今');

        if (0 === count($stats)) {
            $io->info('该时间范围内没有异常记录');

            return;
        }

        $io->text('<comment>异常统计:</comment>');
        $tableData = [];
        foreach ($stats as $stat) {
            if (is_array($stat) && isset($stat['exceptionClass'], $stat['count'])) {
                $tableData[] = [$stat['exceptionClass'], $stat['count']];
            }
        }
        $io->table(['异常类型', '数量'], $tableData);
    }

    public function displayCleanupResults(SymfonyStyle $io, int $operationCleaned, int $exceptionCleaned): void
    {
        $io->success('清理完成：');
        $io->text("- 操作日志：{$operationCleaned} 条");
        $io->text("- 异常日志：{$exceptionCleaned} 条");
        $io->text('- 总计：' . ($operationCleaned + $exceptionCleaned) . ' 条');
    }

    private function displayQueryConditions(
        SymfonyStyle $io,
        ?string $operation,
        ?string $entityType,
        ?int $entityId,
        ?string $level,
        ?\DateTimeImmutable $since,
    ): void {
        $conditions = [];
        if (null !== $operation) {
            $conditions[] = "操作: {$operation}";
        }
        if (null !== $entityType) {
            $conditions[] = "实体类型: {$entityType}";
        }
        if (null !== $entityId) {
            $conditions[] = "实体ID: {$entityId}";
        }
        if (null !== $level) {
            $conditions[] = "级别: {$level}";
        }
        if (null !== $since) {
            $conditions[] = '起始时间: ' . $since->format('Y-m-d H:i:s');
        }

        if (count($conditions) > 0) {
            $io->text('查询条件: ' . implode(', ', $conditions));
        }
    }

    private function displayExceptionQueryConditions(
        SymfonyStyle $io,
        ?string $entityType,
        ?int $entityId,
        ?\DateTimeImmutable $since,
    ): void {
        $conditions = [];
        if (null !== $entityType) {
            $conditions[] = "实体类型: {$entityType}";
        }
        if (null !== $entityId) {
            $conditions[] = "实体ID: {$entityId}";
        }
        if (null !== $since) {
            $conditions[] = '起始时间: ' . $since->format('Y-m-d H:i:s');
        }

        if (count($conditions) > 0) {
            $io->text('查询条件: ' . implode(', ', $conditions));
        }
    }

    /**
     * @param array<AcmeOperationLog> $logs
     */
    private function displayOperationLogsTable(SymfonyStyle $io, array $logs): void
    {
        $tableData = [];
        foreach ($logs as $log) {
            $tableData[] = [
                $log->getId(),
                $log->getOperation(),
                $log->getLevel()->value,
                $log->getEntityType() ?? 'N/A',
                $log->getEntityId() ?? 'N/A',
                $log->getMessage(),
                $log->getOccurredTime()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', '操作', '级别', '实体类型', '实体ID', '消息', '时间'],
            $tableData
        );
    }

    /**
     * @param array<AcmeExceptionLog> $exceptions
     */
    private function displayExceptionLogsTable(SymfonyStyle $io, array $exceptions): void
    {
        $tableData = [];
        foreach ($exceptions as $exception) {
            $tableData[] = [
                $exception->getId(),
                $exception->getExceptionClass(),
                substr($exception->getMessage(), 0, 50) . (strlen($exception->getMessage()) > 50 ? '...' : ''),
                $exception->getEntityType() ?? 'N/A',
                $exception->getEntityId() ?? 'N/A',
                $exception->getOccurredTime()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', '异常类型', '消息', '实体类型', '实体ID', '时间'],
            $tableData
        );
    }

    /**
     * @param array<AcmeOperationLog> $logs
     */
    private function showOperationLogDetails(SymfonyStyle $io, array $logs): void
    {
        if (!$io->confirm('是否查看详细信息？', false)) {
            return;
        }

        foreach ($logs as $log) {
            $this->displaySingleOperationLogDetail($io, $log);

            if (!$io->confirm('继续查看下一条？', true)) {
                break;
            }
        }
    }

    /**
     * @param array<AcmeExceptionLog> $exceptions
     */
    private function showExceptionLogDetails(SymfonyStyle $io, array $exceptions): void
    {
        if (!$io->confirm('是否查看异常详情？', false)) {
            return;
        }

        foreach ($exceptions as $exception) {
            $this->displaySingleExceptionDetail($io, $exception);

            if (!$io->confirm('继续查看下一条？', true)) {
                break;
            }
        }
    }

    private function displaySingleOperationLogDetail(SymfonyStyle $io, AcmeOperationLog $log): void
    {
        $io->section("日志详情 - ID {$log->getId()}");
        $io->definitionList(
            ['操作类型', $log->getOperation()],
            ['消息', $log->getMessage()],
            ['级别', $log->getLevel()->value],
            ['实体类型', $log->getEntityType() ?? 'N/A'],
            ['实体ID', $log->getEntityId() ?? 'N/A'],
            ['发生时间', $log->getOccurredTime()->format('Y-m-d H:i:s')],
            ['详细信息', null !== $log->getContext() ? json_encode($log->getContext(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A']
        );
    }

    private function displaySingleExceptionDetail(SymfonyStyle $io, AcmeExceptionLog $exception): void
    {
        $io->section("异常详情 - ID {$exception->getId()}");
        $io->definitionList(
            ['异常类型', $exception->getExceptionClass()],
            ['消息', $exception->getMessage()],
            ['实体类型', $exception->getEntityType() ?? 'N/A'],
            ['实体ID', $exception->getEntityId() ?? 'N/A'],
            ['发生时间', $exception->getOccurredTime()->format('Y-m-d H:i:s')]
        );

        if (null !== $exception->getStackTrace()) {
            $io->text('<comment>堆栈跟踪:</comment>');
            $io->text($exception->getStackTrace());
        }

        if (null !== $exception->getContext()) {
            $io->text('<comment>上下文信息:</comment>');
            $contextJson = json_encode($exception->getContext(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (false !== $contextJson) {
                $io->text($contextJson);
            }
        }
    }

    /**
     * @param array<string, int> $operationStats
     */
    private function displayOperationTypeStats(SymfonyStyle $io, array $operationStats): void
    {
        $io->text('<comment>操作类型统计:</comment>');
        arsort($operationStats);
        $tableData = [];
        foreach ($operationStats as $operation => $count) {
            $tableData[] = [$operation, $count];
        }
        $io->table(['操作类型', '数量'], $tableData);
    }

    /**
     * @param array<string, int> $levelStats
     */
    private function displayLevelStats(SymfonyStyle $io, array $levelStats): void
    {
        $io->text('<comment>日志级别统计:</comment>');
        arsort($levelStats);
        $tableData = [];
        foreach ($levelStats as $level => $count) {
            $tableData[] = [$level, $count];
        }
        $io->table(['级别', '数量'], $tableData);
    }

    /**
     * @param array<string, int> $entityStats
     */
    private function displayEntityStats(SymfonyStyle $io, array $entityStats): void
    {
        $io->text('<comment>实体类型统计:</comment>');
        arsort($entityStats);
        $tableData = [];
        foreach ($entityStats as $entity => $count) {
            $tableData[] = [$entity, $count];
        }
        $io->table(['实体类型', '数量'], $tableData);
    }
}
