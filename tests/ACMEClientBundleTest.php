<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\ACMEClientBundle\ACMEClientBundle;

class ACMEClientBundleTest extends TestCase
{
    public function testBundleExists(): void
    {
        $this->assertTrue(class_exists(ACMEClientBundle::class));
    }

    public function testBundleExtendsBundle(): void
    {
        $bundle = new ACMEClientBundle();
        $this->assertInstanceOf(Bundle::class, $bundle);
    }
} 