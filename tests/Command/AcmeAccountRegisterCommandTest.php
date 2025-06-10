<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ACMEClientBundle\Command\AcmeAccountRegisterCommand;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\AcmeLogService;

class AcmeAccountRegisterCommandTest extends TestCase
{
    private AcmeAccountRegisterCommand $command;
    private MockObject&AccountService $accountService;
    private MockObject&AcmeLogService $logService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->accountService = $this->createMock(AccountService::class);
        $this->logService = $this->createMock(AcmeLogService::class);

        $this->command = new AcmeAccountRegisterCommand(
            $this->accountService,
            $this->logService
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        $this->assertEquals('acme:account:register', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertEquals('注册ACME账户', $this->command->getDescription());
    }

    public function testExecuteWithValidEmail(): void
    {
        $account = new Account();
        $account->setStatus(AccountStatus::VALID);
        $account->setAccountUrl('https://acme.example.com/account/123');

        $this->accountService->expects($this->once())
            ->method('findAccountByEmail')
            ->with('user@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory')
            ->willReturn(null);

        $this->accountService->expects($this->once())
            ->method('registerAccountByEmail')
            ->with('user@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory', 2048)
            ->willReturn($account);

        $this->accountService->expects($this->once())
            ->method('getEmailFromAccount')
            ->with($account)
            ->willReturn('user@example.com');

        $this->logService->expects($this->once())
            ->method('logAccountOperation');

        $this->commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('账户注册成功', $output);
        $this->assertStringContainsString('user@example.com', $output);
    }

    public function testExecuteWithInvalidEmail(): void
    {
        $this->commandTester->execute([
            'email' => 'invalid-email',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('提供的邮箱地址无效', $output);
    }

    public function testExecuteWithExistingAccount(): void
    {
        $account = new Account();
        $account->setStatus(AccountStatus::VALID);
        $account->setAccountUrl('https://acme.example.com/account/123');

        $this->accountService->expects($this->once())
            ->method('findAccountByEmail')
            ->with('user@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory')
            ->willReturn($account);

        $this->accountService->expects($this->once())
            ->method('getEmailFromAccount')
            ->with($account)
            ->willReturn('user@example.com');

        $this->commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('账户已存在', $output);
    }

    public function testExecuteWithCustomOptions(): void
    {
        $account = new Account();
        $account->setStatus(AccountStatus::VALID);

        $this->accountService->expects($this->once())
            ->method('findAccountByEmail')
            ->with('user@example.com', 'https://acme-v02.api.letsencrypt.org/directory')
            ->willReturn(null);

        $this->accountService->expects($this->once())
            ->method('registerAccountByEmail')
            ->with('user@example.com', 'https://acme-v02.api.letsencrypt.org/directory', 4096)
            ->willReturn($account);

        $this->accountService->expects($this->once())
            ->method('getEmailFromAccount')
            ->willReturn('user@example.com');

        $this->logService->expects($this->once())
            ->method('logAccountOperation');

        $this->commandTester->execute([
            'email' => 'user@example.com',
            '--directory-url' => 'https://acme-v02.api.letsencrypt.org/directory',
            '--key-size' => '4096',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('私钥大小: 4096 位', $output);
        $this->assertStringContainsString('https://acme-v02.api.letsencrypt.org/directory', $output);
    }

    public function testExecuteWithAcmeClientException(): void
    {
        $this->accountService->expects($this->once())
            ->method('findAccountByEmail')
            ->willReturn(null);

        $this->accountService->expects($this->once())
            ->method('registerAccountByEmail')
            ->willThrowException(new AcmeClientException('Registration failed'));

                 $this->logService->expects($this->once())
             ->method('logAccountOperation')
             ->with(
                 'register_failed',
                 '账户注册失败: Registration failed',
                 null,
                 $this->callback(function($context) {
                     return isset($context['email']) && 
                            isset($context['error']) &&
                            $context['error'] === 'Registration failed';
                 })
             );

        $this->commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('账户注册失败: Registration failed', $output);
    }

    public function testExecuteWithUnexpectedException(): void
    {
        $this->accountService->expects($this->once())
            ->method('findAccountByEmail')
            ->willReturn(null);

        $this->accountService->expects($this->once())
            ->method('registerAccountByEmail')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $this->logService->expects($this->once())
            ->method('logException');

        $this->commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('发生未知错误: Unexpected error', $output);
        $this->assertStringContainsString('详细错误信息已记录到日志中', $output);
    }

    public function testCommandArguments(): void
    {
        $definition = $this->command->getDefinition();
        
        $this->assertTrue($definition->hasArgument('email'));
        $this->assertTrue($definition->getArgument('email')->isRequired());
        $this->assertEquals('账户邮箱地址', $definition->getArgument('email')->getDescription());
    }

    public function testCommandOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('directory-url'));
        $this->assertTrue($definition->hasOption('key-size'));
        $this->assertTrue($definition->hasOption('agree-tos'));

        $this->assertEquals('https://acme-staging-v02.api.letsencrypt.org/directory', 
            $definition->getOption('directory-url')->getDefault());
        $this->assertEquals('2048', $definition->getOption('key-size')->getDefault());
        $this->assertFalse($definition->getOption('agree-tos')->getDefault());
    }
}
