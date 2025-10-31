<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Contract\QueryHandlerInterface;
use Tourze\ACMEClientBundle\Service\LogDisplayService;
use Tourze\ACMEClientBundle\Service\LogQueryService;

class CleanupHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly LogQueryService $logQueryService,
        private readonly LogDisplayService $logDisplayService,
    ) {
    }

    public function supports(array $options): bool
    {
        return null !== $options['cleanup'];
    }

    public function handle(SymfonyStyle $io, array $options): int
    {
        $cleanupRaw = $options['cleanup'] ?? null;
        $days = \is_numeric($cleanupRaw) ? (int) $cleanupRaw : 0;

        if (!$this->confirmCleanupOperation($io, $days)) {
            $io->info('用户取消操作');

            return Command::SUCCESS;
        }

        return $this->executeCleanupOperation($io, $days);
    }

    private function confirmCleanupOperation(SymfonyStyle $io, int $days): bool
    {
        $io->section('ACME 日志清理');
        $io->warning("将清理 {$days} 天前的所有日志（操作日志和异常日志）");

        return $io->confirm('确认执行清理操作吗？此操作不可逆！', false);
    }

    private function executeCleanupOperation(SymfonyStyle $io, int $days): int
    {
        try {
            $cleanupResult = $this->logQueryService->cleanupLogs($days);
            $operationCleaned = (int) $cleanupResult[0];
            $exceptionCleaned = (int) $cleanupResult[1];
            $this->logDisplayService->displayCleanupResults($io, $operationCleaned, $exceptionCleaned);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("清理过程中发生错误: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
