<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ACMEClientBundle\Command\AcmeOrderCreateCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeOrderCreateCommand::class)]
#[RunTestsInSeparateProcesses]
final class AcmeOrderCreateCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(AcmeOrderCreateCommand::class);

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    public function testCommandCanBeInstantiatedFromContainer(): void
    {
        $command = self::getService(AcmeOrderCreateCommand::class);
        $this->assertInstanceOf(AcmeOrderCreateCommand::class, $command);
        $this->assertSame('acme:order:create', $command->getName());
    }

    public function testExecuteCommandWithInvalidDomain(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令传入无效域名
        $commandTester->execute([
            'account-id' => '1',
            'domains' => 'invalid domain',
        ]);

        // 验证命令因无效域名而失败
        $this->assertSame(1, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('无效的域名格式', $output);
    }

    public function testExecuteCommandWithEmptyDomains(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令传入空域名
        $commandTester->execute([
            'account-id' => '1',
            'domains' => '',
        ]);

        // 验证命令因空域名而失败
        $this->assertSame(1, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('域名列表不能为空', $output);
    }

    public function testArgumentAccountId(): void
    {
        $command = self::getService(AcmeOrderCreateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('account-id'));
        $this->assertTrue($definition->getArgument('account-id')->isRequired());
        $this->assertEquals('账户ID', $definition->getArgument('account-id')->getDescription());
    }

    public function testArgumentDomains(): void
    {
        $command = self::getService(AcmeOrderCreateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('domains'));
        $this->assertTrue($definition->getArgument('domains')->isRequired());
        $this->assertEquals('域名列表（逗号分隔）', $definition->getArgument('domains')->getDescription());
    }

    public function testOptionWaitValidation(): void
    {
        $command = self::getService(AcmeOrderCreateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('wait-validation'));
        $this->assertFalse($definition->getOption('wait-validation')->getDefault());
    }

    public function testOptionAutoDownload(): void
    {
        $command = self::getService(AcmeOrderCreateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('auto-download'));
        $this->assertFalse($definition->getOption('auto-download')->getDefault());
    }

    public function testOptionTimeout(): void
    {
        $command = self::getService(AcmeOrderCreateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('timeout'));
        $this->assertEquals('300', $definition->getOption('timeout')->getDefault());
    }
}
