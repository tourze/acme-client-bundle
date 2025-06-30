<?php

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Repository\AcmeExceptionLogRepository;

class AcmeExceptionLogRepositoryTest extends TestCase
{
    public function testRepositoryExists(): void
    {
        $this->assertTrue(class_exists(AcmeExceptionLogRepository::class));
    }
}