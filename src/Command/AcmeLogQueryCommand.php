<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Service\AcmeExceptionService;
use Tourze\ACMEClientBundle\Service\AcmeLogService;

/**
 * ACME 日志查询命令
 */
#[AsCommand(
    name: 'acme:log:query',
    description: '查询ACME操作日志和异常日志',
)]
class AcmeLogQueryCommand extends Command
{
    public function __construct(
        private readonly AcmeLogService $logService,
        private readonly AcmeExceptionService $exceptionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, '日志类型 (operation|exception)', 'operation')
            ->addOption('operation', 'o', InputOption::VALUE_OPTIONAL, '操作类型 (register|create|download|renew等)')
            ->addOption('entity-type', 'e', InputOption::VALUE_OPTIONAL, '实体类型 (account|order|challenge|certificate)')
            ->addOption('entity-id', null, InputOption::VALUE_OPTIONAL, '实体ID')
            ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, '日志级别 (info|warning|error)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, '查询条数限制', '50')
            ->addOption('since', 's', InputOption::VALUE_OPTIONAL, '起始时间 (格式: Y-m-d H:i:s 或相对时间如 "1 hour ago")')
            ->addOption('stats', null, InputOption::VALUE_NONE, '显示统计信息')
            ->addOption('cleanup', null, InputOption::VALUE_OPTIONAL, '清理N天前的日志', null)
            ->setHelp('
此命令用于查询和管理ACME操作日志。

查询示例:
  <info>php bin/console acme:log:query</info>                                   # 查询最近50条操作日志
  <info>php bin/console acme:log:query --type=exception</info>                 # 查询异常日志
  <info>php bin/console acme:log:query --operation=register</info>             # 查询账户注册操作
  <info>php bin/console acme:log:query --entity-type=certificate --entity-id=123</info>  # 查询指定证书的日志
  <info>php bin/console acme:log:query --level=error</info>                    # 查询错误级别日志
  <info>php bin/console acme:log:query --since="1 day ago"</info>              # 查询1天内的日志
  <info>php bin/console acme:log:query --stats</info>                          # 显示统计信息

管理示例:
  <info>php bin/console acme:log:query --cleanup=30</info>                     # 清理30天前的日志

支持的操作类型:
  - register, register_failed (账户注册)
  - create, create_failed (订单创建)
  - download, download_failed (证书下载)
  - renew, renew_started, renew_failed (证书续订)

支持的实体类型:
  - account (账户)
  - order (订单)
  - challenge (质询)
  - certificate (证书)
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $input->getOption('type');
        $operation = $input->getOption('operation');
        $entityType = $input->getOption('entity-type');
        $entityId = $input->getOption('entity-id') ? (int) $input->getOption('entity-id') : null;
        $level = $input->getOption('level');
        $limit = (int) $input->getOption('limit');
        $sinceInput = $input->getOption('since');
        $showStats = $input->getOption('stats');
        $cleanup = $input->getOption('cleanup');

        // 处理清理操作
        if ($cleanup !== null) {
            $cleanupDays = (int) $cleanup;
            return $this->handleCleanup($io, $cleanupDays);
        }

        // 处理统计信息
        if ($showStats) {
            return $this->handleStats($io, $type, $sinceInput);
        }

        // 解析起始时间
        $since = null;
        if ($sinceInput) {
            try {
                $since = new \DateTimeImmutable($sinceInput);
            } catch (\Exception $e) {
                $io->error("无效的时间格式: {$sinceInput}");
                return Command::FAILURE;
            }
        }

        try {
            $io->section('ACME 日志查询');

            if ($type === 'exception') {
                return $this->queryExceptionLogs($io, $entityType, $entityId, $since, $limit);
            } else {
                return $this->queryOperationLogs($io, $operation, $entityType, $entityId, $level, $since, $limit);
            }
        } catch (\Throwable $e) {
            $io->error("查询日志时发生错误: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function queryOperationLogs(
        SymfonyStyle $io,
        ?string $operation,
        ?string $entityType,
        ?int $entityId,
        ?string $level,
        ?\DateTimeImmutable $since,
        int $limit
    ): int {
        $logs = $this->logService->findLogs($operation, $entityType, $entityId, $level, $limit);

        if ($since) {
            $logs = array_filter($logs, fn($log) => $log->getOccurredAt() >= $since);
        }

        if (empty($logs)) {
            $io->info('没有找到匹配的操作日志');
            return Command::SUCCESS;
        }

        $io->info("找到 " . count($logs) . " 条操作日志");

        // 显示查询条件
        $conditions = [];
        if ($operation) $conditions[] = "操作: {$operation}";
        if ($entityType) $conditions[] = "实体类型: {$entityType}";
        if ($entityId) $conditions[] = "实体ID: {$entityId}";
        if ($level) $conditions[] = "级别: {$level}";
        if ($since) $conditions[] = "起始时间: " . $since->format('Y-m-d H:i:s');

        if (!empty($conditions)) {
            $io->text("查询条件: " . implode(', ', $conditions));
        }

        // 显示日志表格
        $tableData = [];
        foreach ($logs as $log) {
            $tableData[] = [
                $log->getId(),
                $log->getOperationType(),
                $log->getLevel(),
                $log->getEntityType() ?? 'N/A',
                $log->getEntityId() ?? 'N/A',
                $log->getMessage(),
                $log->getOccurredAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', '操作', '级别', '实体类型', '实体ID', '消息', '时间'],
            $tableData
        );

        // 显示详细信息选项
        if ($io->confirm('是否查看详细信息？', false)) {
            foreach ($logs as $log) {
                $io->section("日志详情 - ID {$log->getId()}");
                $io->definitionList(
                    ['操作类型', $log->getOperationType()],
                    ['消息', $log->getMessage()],
                    ['级别', $log->getLevel()],
                    ['实体类型', $log->getEntityType() ?? 'N/A'],
                    ['实体ID', $log->getEntityId() ?? 'N/A'],
                    ['发生时间', $log->getOccurredAt()->format('Y-m-d H:i:s')],
                    ['详细信息', $log->getDetails() ? json_encode($log->getDetails(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A']
                );

                if (!$io->confirm('继续查看下一条？', true)) {
                    break;
                }
            }
        }

        return Command::SUCCESS;
    }

    private function queryExceptionLogs(
        SymfonyStyle $io,
        ?string $entityType,
        ?int $entityId,
        ?\DateTimeImmutable $since,
        int $limit
    ): int {
        $exceptions = $this->exceptionService->findExceptions(null, $entityType, $entityId, $since, $limit);

        if (empty($exceptions)) {
            $io->info('没有找到匹配的异常日志');
            return Command::SUCCESS;
        }

        $io->info("找到 " . count($exceptions) . " 条异常日志");

        // 显示查询条件
        $conditions = [];
        if ($entityType) $conditions[] = "实体类型: {$entityType}";
        if ($entityId) $conditions[] = "实体ID: {$entityId}";
        if ($since) $conditions[] = "起始时间: " . $since->format('Y-m-d H:i:s');

        if (!empty($conditions)) {
            $io->text("查询条件: " . implode(', ', $conditions));
        }

        // 显示异常表格
        $tableData = [];
        foreach ($exceptions as $exception) {
            $tableData[] = [
                $exception->getId(),
                $exception->getExceptionClass(),
                substr($exception->getMessage(), 0, 50) . (strlen($exception->getMessage()) > 50 ? '...' : ''),
                $exception->getEntityType() ?? 'N/A',
                $exception->getEntityId() ?? 'N/A',
                $exception->getOccurredAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', '异常类型', '消息', '实体类型', '实体ID', '时间'],
            $tableData
        );

        // 显示详细信息选项
        if ($io->confirm('是否查看异常详情？', false)) {
            foreach ($exceptions as $exception) {
                $io->section("异常详情 - ID {$exception->getId()}");
                $io->definitionList(
                    ['异常类型', $exception->getExceptionClass()],
                    ['消息', $exception->getMessage()],
                    ['实体类型', $exception->getEntityType() ?? 'N/A'],
                    ['实体ID', $exception->getEntityId() ?? 'N/A'],
                    ['发生时间', $exception->getOccurredAt()->format('Y-m-d H:i:s')]
                );

                if ($exception->getStackTrace()) {
                    $io->text('<comment>堆栈跟踪:</comment>');
                    $io->text($exception->getStackTrace());
                }

                if ($exception->getContext()) {
                    $io->text('<comment>上下文信息:</comment>');
                    $io->text(json_encode($exception->getContext(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                if (!$io->confirm('继续查看下一条？', true)) {
                    break;
                }
            }
        }

        return Command::SUCCESS;
    }

    private function handleStats(SymfonyStyle $io, string $type, ?string $sinceInput): int
    {
        $since = null;
        if ($sinceInput) {
            try {
                $since = new \DateTimeImmutable($sinceInput);
            } catch (\Exception $e) {
                $io->error("无效的时间格式: {$sinceInput}");
                return Command::FAILURE;
            }
        } else {
            $since = new \DateTimeImmutable('-7 days'); // 默认最近7天
        }

        $io->section('ACME 日志统计信息');
        $io->text("统计时间范围: " . $since->format('Y-m-d H:i:s') . " 至今");

        if ($type === 'exception') {
            $stats = $this->exceptionService->getExceptionStats($since);

            if (empty($stats)) {
                $io->info('该时间范围内没有异常记录');
                return Command::SUCCESS;
            }

            $io->text('<comment>异常统计:</comment>');
            $tableData = [];
            foreach ($stats as $stat) {
                $tableData[] = [$stat['exceptionClass'], $stat['count']];
            }
            $io->table(['异常类型', '数量'], $tableData);
        } else {
            // 操作日志统计
            $logs = $this->logService->findLogs(null, null, null, null, 1000);
            $logs = array_filter($logs, fn($log) => $log->getOccurredAt() >= $since);

            if (empty($logs)) {
                $io->info('该时间范围内没有操作记录');
                return Command::SUCCESS;
            }

            // 按操作类型统计
            $operationStats = [];
            $levelStats = [];
            $entityStats = [];

            foreach ($logs as $log) {
                $operationStats[$log->getOperationType()] = ($operationStats[$log->getOperationType()] ?? 0) + 1;
                $levelStats[$log->getLevel()] = ($levelStats[$log->getLevel()] ?? 0) + 1;
                if ($log->getEntityType()) {
                    $entityStats[$log->getEntityType()] = ($entityStats[$log->getEntityType()] ?? 0) + 1;
                }
            }

            $io->text('<comment>操作类型统计:</comment>');
            arsort($operationStats);
            $tableData = [];
            foreach ($operationStats as $operation => $count) {
                $tableData[] = [$operation, $count];
            }
            $io->table(['操作类型', '数量'], $tableData);

            $io->text('<comment>日志级别统计:</comment>');
            arsort($levelStats);
            $tableData = [];
            foreach ($levelStats as $level => $count) {
                $tableData[] = [$level, $count];
            }
            $io->table(['级别', '数量'], $tableData);

            if (!empty($entityStats)) {
                $io->text('<comment>实体类型统计:</comment>');
                arsort($entityStats);
                $tableData = [];
                foreach ($entityStats as $entity => $count) {
                    $tableData[] = [$entity, $count];
                }
                $io->table(['实体类型', '数量'], $tableData);
            }
        }

        return Command::SUCCESS;
    }

    private function handleCleanup(SymfonyStyle $io, int $days): int
    {
        if ($days < 1) {
            $io->error('清理天数必须大于0');
            return Command::FAILURE;
        }

        $io->section('ACME 日志清理');
        $io->warning("将清理 {$days} 天前的所有日志（操作日志和异常日志）");

        if (!$io->confirm('确认执行清理操作吗？此操作不可逆！', false)) {
            $io->info('用户取消操作');
            return Command::SUCCESS;
        }

        try {
            $operationCleaned = $this->logService->cleanupOldLogs($days);
            $exceptionCleaned = $this->exceptionService->cleanupOldExceptions($days);

            $io->success("清理完成：");
            $io->text("- 操作日志：{$operationCleaned} 条");
            $io->text("- 异常日志：{$exceptionCleaned} 条");
            $io->text("- 总计：" . ($operationCleaned + $exceptionCleaned) . " 条");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("清理过程中发生错误: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
