<?php

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;

class AuthorizationRepositoryTest extends TestCase
{
    public function testRepositoryExists(): void
    {
        $this->assertTrue(class_exists(AuthorizationRepository::class));
    }
}