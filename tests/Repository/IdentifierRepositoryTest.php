<?php

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Repository\IdentifierRepository;

class IdentifierRepositoryTest extends TestCase
{
    public function testRepositoryExists(): void
    {
        $this->assertTrue(class_exists(IdentifierRepository::class));
    }
}