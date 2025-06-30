<?php

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Repository\AcmeOperationLogRepository;

class AcmeOperationLogRepositoryTest extends TestCase
{
    public function testRepositoryExists(): void
    {
        $this->assertTrue(class_exists(AcmeOperationLogRepository::class));
    }
}