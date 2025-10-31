<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Contract;

use Symfony\Component\Console\Style\SymfonyStyle;

interface QueryHandlerInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function handle(SymfonyStyle $io, array $options): int;

    /**
     * @param array<string, mixed> $options
     */
    public function supports(array $options): bool;
}
