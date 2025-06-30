<?php

namespace Tourze\ACMEClientBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\DependencyInjection\ACMEClientExtension;

class ACMEClientExtensionTest extends TestCase
{
    public function testExtensionExists(): void
    {
        $this->assertTrue(class_exists(ACMEClientExtension::class));
    }
}