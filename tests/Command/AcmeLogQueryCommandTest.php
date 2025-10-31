<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ACMEClientBundle\Command\AcmeLogQueryCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeLogQueryCommand::class)]
#[RunTestsInSeparateProcesses]
final class AcmeLogQueryCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(AcmeLogQueryCommand::class);

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // 集成测试的初始化逻辑
    }

    public function testCommandCanBeInstantiatedFromContainer(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $this->assertInstanceOf(AcmeLogQueryCommand::class, $command);
        $this->assertSame('acme:log:query', $command->getName());
    }

    public function testExecuteCommand(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令查询操作日志
        $commandTester->execute([
            '--limit' => '10',
        ]);

        // 验证命令成功执行
        $output = $commandTester->getDisplay();
        $statusCode = $commandTester->getStatusCode();
        if (0 !== $statusCode) {
            echo 'Command output: ' . $output . "\n";
        }
        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('ACME 日志查询', $output);
    }

    public function testExecuteCommandWithExceptionType(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令查询异常日志
        $commandTester->execute([
            '--type' => 'exception',
            '--limit' => '10',
        ]);

        // 验证命令成功执行
        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('ACME 日志查询', $output);
    }

    public function testOptionType(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('type'));
        $this->assertEquals('operation', $definition->getOption('type')->getDefault());
    }

    public function testOptionOperation(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('operation'));
        $this->assertNull($definition->getOption('operation')->getDefault());
    }

    public function testOptionEntityType(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('entity-type'));
        $this->assertNull($definition->getOption('entity-type')->getDefault());
    }

    public function testOptionEntityId(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('entity-id'));
        $this->assertNull($definition->getOption('entity-id')->getDefault());
    }

    public function testOptionLevel(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('level'));
        $this->assertNull($definition->getOption('level')->getDefault());
    }

    public function testOptionLimit(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('limit'));
        $this->assertEquals('50', $definition->getOption('limit')->getDefault());
    }

    public function testOptionSince(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('since'));
        $this->assertNull($definition->getOption('since')->getDefault());
    }

    public function testOptionStats(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('stats'));
        $this->assertFalse($definition->getOption('stats')->getDefault());
    }

    public function testOptionCleanup(): void
    {
        $command = self::getService(AcmeLogQueryCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('cleanup'));
        $this->assertNull($definition->getOption('cleanup')->getDefault());
    }
}
