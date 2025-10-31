<?php

namespace Tourze\ACMEClientBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class ACMEClientExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
