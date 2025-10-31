<?php

namespace Tourze\ACMEClientBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\EasyAdminEnumFieldBundle\EasyAdminEnumFieldBundle;

class ACMEClientBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            EasyAdminEnumFieldBundle::class => ['all' => true],
        ];
    }
}
