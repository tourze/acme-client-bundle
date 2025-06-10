<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Command\AcmeLogQueryCommand;

class AcmeLogQueryCommandTest extends TestCase
{
    public function testCommandExists(): void
    {
        $this->assertTrue(class_exists(AcmeLogQueryCommand::class));
    }
}
