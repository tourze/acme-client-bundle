<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Contract\QueryHandlerInterface;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Service\LogDisplayService;
use Tourze\ACMEClientBundle\Service\LogQueryService;

class StatsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly LogQueryService $logQueryService,
        private readonly LogDisplayService $logDisplayService,
    ) {
    }

    public function supports(array $options): bool
    {
        return (bool) $options['showStats'];
    }

    public function handle(SymfonyStyle $io, array $options): int
    {
        try {
            $sinceInputRaw = $options['sinceInput'] ?? null;
            $sinceInput = \is_string($sinceInputRaw) ? $sinceInputRaw : null;
            $since = $this->logQueryService->parseSinceTime($sinceInput);

            $typeRaw = $options['type'] ?? 'operation';
            $type = \is_string($typeRaw) ? $typeRaw : 'operation';

            if ('exception' === $type) {
                $stats = $this->logQueryService->getExceptionStatistics($since);
                $this->logDisplayService->displayExceptionStatistics($io, $stats, $since);
            } else {
                $stats = $this->logQueryService->getOperationStatistics($since);
                $this->logDisplayService->displayOperationStatistics($io, $stats, $since);
            }

            return Command::SUCCESS;
        } catch (AbstractAcmeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
