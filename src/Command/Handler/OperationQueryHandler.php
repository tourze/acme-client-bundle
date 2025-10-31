<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Contract\QueryHandlerInterface;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Service\LogDisplayService;
use Tourze\ACMEClientBundle\Service\LogQueryService;

class OperationQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly LogQueryService $logQueryService,
        private readonly LogDisplayService $logDisplayService,
    ) {
    }

    public function supports(array $options): bool
    {
        return 'operation' === $options['type'];
    }

    public function handle(SymfonyStyle $io, array $options): int
    {
        try {
            $sinceInputRaw = $options['sinceInput'] ?? null;
            $sinceInput = \is_string($sinceInputRaw) ? $sinceInputRaw : null;
            $since = $this->logQueryService->parseSinceTime($sinceInput);

            $operationRaw = $options['operation'] ?? null;
            $operation = \is_string($operationRaw) ? $operationRaw : null;

            $entityTypeRaw = $options['entityType'] ?? null;
            $entityType = \is_string($entityTypeRaw) ? $entityTypeRaw : null;

            $entityIdRaw = $options['entityId'] ?? null;
            $entityId = \is_int($entityIdRaw) ? $entityIdRaw : null;

            $levelRaw = $options['level'] ?? null;
            $level = \is_string($levelRaw) ? $levelRaw : null;

            $limitRaw = $options['limit'] ?? 50;
            $limit = \is_int($limitRaw) ? $limitRaw : 50;

            $logs = $this->logQueryService->queryOperationLogs(
                $operation,
                $entityType,
                $entityId,
                $level,
                $since,
                $limit
            );

            $this->logDisplayService->displayOperationLogsResults(
                $io,
                $logs,
                $operation,
                $entityType,
                $entityId,
                $level,
                $since
            );

            return Command::SUCCESS;
        } catch (AbstractAcmeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
