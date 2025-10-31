<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ACMEClientBundle\Command\AcmeCertRenewCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeCertRenewCommand::class)]
#[RunTestsInSeparateProcesses]
final class AcmeCertRenewCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(AcmeCertRenewCommand::class);

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // 集成测试的初始化逻辑
    }

    public function testCommandCanBeInstantiatedFromContainer(): void
    {
        $command = self::getService(AcmeCertRenewCommand::class);
        $this->assertInstanceOf(AcmeCertRenewCommand::class, $command);
        $this->assertSame('acme:cert:renew', $command->getName());
    }

    public function testExecuteCommand(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令带dry-run选项以避免实际执行续订
        $commandTester->execute([
            '--dry-run' => true,
        ]);

        // 验证命令成功执行
        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('ACME 证书续订', $output);
        $this->assertStringContainsString('模拟运行模式', $output);
    }

    public function testArgumentCertificateId(): void
    {
        $command = self::getService(AcmeCertRenewCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('certificate-id'));
        $this->assertFalse($definition->getArgument('certificate-id')->isRequired());
        $this->assertEquals('证书ID（可选，如果不提供则续订所有即将过期的证书）',
            $definition->getArgument('certificate-id')->getDescription());
    }

    public function testOptionDaysBeforeExpiry(): void
    {
        $command = self::getService(AcmeCertRenewCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('days-before-expiry'));
        $this->assertEquals('30', $definition->getOption('days-before-expiry')->getDefault());
    }

    public function testOptionForce(): void
    {
        $command = self::getService(AcmeCertRenewCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
        $this->assertFalse($definition->getOption('force')->getDefault());
    }

    public function testOptionDryRun(): void
    {
        $command = self::getService(AcmeCertRenewCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->getDefault());
    }
}
