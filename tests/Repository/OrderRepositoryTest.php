<?php

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Repository\OrderRepository;

class OrderRepositoryTest extends TestCase
{
    public function testRepositoryExists(): void
    {
        $this->assertTrue(class_exists(OrderRepository::class));
    }
}