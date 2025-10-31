<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ACMEClientBundle\Command\AcmeAccountRegisterCommand;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\AcmeLogService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeAccountRegisterCommand::class)]
#[RunTestsInSeparateProcesses]
final class AcmeAccountRegisterCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试的初始化逻辑
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);

        return new CommandTester($command);
    }

    public function testCommandName(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $this->assertEquals('acme:account:register', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $this->assertEquals('注册ACME账户', $command->getDescription());
    }

    public function testExecuteWithValidEmail(): void
    {
        // 使用 mock 服务替换容器中的服务
        /*
         * 使用具体类 AccountService 的 Mock 对象
         * 原因：AccountService 是核心账户管理服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的 ACME 账户操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AccountService 创建接口抽象，但会增加架构复杂度
         */
        $accountServiceMock = $this->createMock(AccountService::class);
        /*
         * 使用具体类 AcmeLogService 的 Mock 对象
         * 原因：AcmeLogService 是核心日志服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的日志操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AcmeLogService 创建接口抽象，但会增加架构复杂度
         */
        $logServiceMock = $this->createMock(AcmeLogService::class);

        // 替换容器中的服务
        self::getContainer()->set(AccountService::class, $accountServiceMock);
        self::getContainer()->set(AcmeLogService::class, $logServiceMock);

        $commandTester = $this->getCommandTester();

        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setStatus(AccountStatus::VALID);
        $account->setAccountUrl('https://acme.example.com/account/123');

        $accountServiceMock->expects($this->once())
            ->method('findAccountByEmail')
            ->with('user@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory')
            ->willReturn(null)
        ;

        $accountServiceMock->expects($this->once())
            ->method('registerAccountByEmail')
            ->with('user@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory', 2048)
            ->willReturn($account)
        ;

        $accountServiceMock->expects($this->once())
            ->method('getEmailFromAccount')
            ->with($account)
            ->willReturn('user@example.com')
        ;

        $logServiceMock->expects($this->once())
            ->method('logAccountOperation')
        ;

        $commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户注册成功', $output);
        $this->assertStringContainsString('user@example.com', $output);
    }

    public function testExecuteWithInvalidEmail(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            'email' => 'invalid-email',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('提供的邮箱地址无效', $output);
    }

    public function testExecuteWithExistingAccount(): void
    {
        // 使用 mock 服务替换容器中的服务
        /*
         * 使用具体类 AccountService 的 Mock 对象
         * 原因：AccountService 是核心账户管理服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的 ACME 账户操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AccountService 创建接口抽象，但会增加架构复杂度
         */
        $accountServiceMock = $this->createMock(AccountService::class);
        /*
         * 使用具体类 AcmeLogService 的 Mock 对象
         * 原因：AcmeLogService 是核心日志服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的日志操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AcmeLogService 创建接口抽象，但会增加架构复杂度
         */
        $logServiceMock = $this->createMock(AcmeLogService::class);

        // 替换容器中的服务
        self::getContainer()->set(AccountService::class, $accountServiceMock);
        self::getContainer()->set(AcmeLogService::class, $logServiceMock);

        $commandTester = $this->getCommandTester();
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setStatus(AccountStatus::VALID);
        $account->setAccountUrl('https://acme.example.com/account/123');

        $accountServiceMock->expects($this->once())
            ->method('findAccountByEmail')
            ->with('user@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory')
            ->willReturn($account)
        ;

        $accountServiceMock->expects($this->once())
            ->method('getEmailFromAccount')
            ->with($account)
            ->willReturn('user@example.com')
        ;

        $commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户已存在', $output);
    }

    public function testExecuteWithCustomOptions(): void
    {
        // 使用 mock 服务替换容器中的服务
        /*
         * 使用具体类 AccountService 的 Mock 对象
         * 原因：AccountService 是核心账户管理服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的 ACME 账户操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AccountService 创建接口抽象，但会增加架构复杂度
         */
        $accountServiceMock = $this->createMock(AccountService::class);
        /*
         * 使用具体类 AcmeLogService 的 Mock 对象
         * 原因：AcmeLogService 是核心日志服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的日志操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AcmeLogService 创建接口抽象，但会增加架构复杂度
         */
        $logServiceMock = $this->createMock(AcmeLogService::class);

        // 替换容器中的服务
        self::getContainer()->set(AccountService::class, $accountServiceMock);
        self::getContainer()->set(AcmeLogService::class, $logServiceMock);

        $commandTester = $this->getCommandTester();
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setStatus(AccountStatus::VALID);

        $accountServiceMock->expects($this->once())
            ->method('findAccountByEmail')
            ->with('user@example.com', 'https://acme-v02.api.letsencrypt.org/directory')
            ->willReturn(null)
        ;

        $accountServiceMock->expects($this->once())
            ->method('registerAccountByEmail')
            ->with('user@example.com', 'https://acme-v02.api.letsencrypt.org/directory', 4096)
            ->willReturn($account)
        ;

        $accountServiceMock->expects($this->once())
            ->method('getEmailFromAccount')
            ->willReturn('user@example.com')
        ;

        $logServiceMock->expects($this->once())
            ->method('logAccountOperation')
        ;

        $commandTester->execute([
            'email' => 'user@example.com',
            '--directory-url' => 'https://acme-v02.api.letsencrypt.org/directory',
            '--key-size' => '4096',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('私钥大小: 4096 位', $output);
        $this->assertStringContainsString('https://acme-v02.api.letsencrypt.org/directory', $output);
    }

    public function testExecuteWithAbstractAcmeException(): void
    {
        // 使用 mock 服务替换容器中的服务
        /*
         * 使用具体类 AccountService 的 Mock 对象
         * 原因：AccountService 是核心账户管理服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的 ACME 账户操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AccountService 创建接口抽象，但会增加架构复杂度
         */
        $accountServiceMock = $this->createMock(AccountService::class);
        /*
         * 使用具体类 AcmeLogService 的 Mock 对象
         * 原因：AcmeLogService 是核心日志服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的日志操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AcmeLogService 创建接口抽象，但会增加架构复杂度
         */
        $logServiceMock = $this->createMock(AcmeLogService::class);

        // 替换容器中的服务
        self::getContainer()->set(AccountService::class, $accountServiceMock);
        self::getContainer()->set(AcmeLogService::class, $logServiceMock);

        $commandTester = $this->getCommandTester();
        $accountServiceMock->expects($this->once())
            ->method('findAccountByEmail')
            ->willReturn(null)
        ;

        $accountServiceMock->expects($this->once())
            ->method('registerAccountByEmail')
            ->willThrowException(new AcmeOperationException('Registration failed'))
        ;

        $logServiceMock->expects($this->once())
            ->method('logAccountOperation')
            ->with(
                'register_failed',
                '账户注册失败: Registration failed',
                null,
                self::callback(function ($context) {
                    return isset($context['email'])
                           && isset($context['error'])
                           && 'Registration failed' === $context['error'];
                })
            )
        ;

        $commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户注册失败: Registration failed', $output);
    }

    public function testExecuteWithUnexpectedException(): void
    {
        // 使用 mock 服务替换容器中的服务
        /*
         * 使用具体类 AccountService 的 Mock 对象
         * 原因：AccountService 是核心账户管理服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的 ACME 账户操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AccountService 创建接口抽象，但会增加架构复杂度
         */
        $accountServiceMock = $this->createMock(AccountService::class);
        /*
         * 使用具体类 AcmeLogService 的 Mock 对象
         * 原因：AcmeLogService 是核心日志服务类，没有对应的接口抽象
         * 合理性：命令测试需要验证具体的日志操作行为，使用 Mock 是必要的
         * 替代方案：可以考虑为 AcmeLogService 创建接口抽象，但会增加架构复杂度
         */
        $logServiceMock = $this->createMock(AcmeLogService::class);

        // 替换容器中的服务
        self::getContainer()->set(AccountService::class, $accountServiceMock);
        self::getContainer()->set(AcmeLogService::class, $logServiceMock);

        $commandTester = $this->getCommandTester();
        $accountServiceMock->expects($this->once())
            ->method('findAccountByEmail')
            ->willReturn(null)
        ;

        $accountServiceMock->expects($this->once())
            ->method('registerAccountByEmail')
            ->willThrowException(new \RuntimeException('Unexpected error'))
        ;

        $logServiceMock->expects($this->once())
            ->method('logException')
        ;

        $commandTester->execute([
            'email' => 'user@example.com',
            '--agree-tos' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('发生未知错误: Unexpected error', $output);
        $this->assertStringContainsString('详细错误信息已记录到日志中', $output);
    }

    public function testCommandArguments(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('email'));
        $this->assertTrue($definition->getArgument('email')->isRequired());
        $this->assertEquals('账户邮箱地址', $definition->getArgument('email')->getDescription());
    }

    public function testCommandOptions(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('directory-url'));
        $this->assertTrue($definition->hasOption('key-size'));
        $this->assertTrue($definition->hasOption('agree-tos'));

        $this->assertEquals('https://acme-staging-v02.api.letsencrypt.org/directory',
            $definition->getOption('directory-url')->getDefault());
        $this->assertEquals('2048', $definition->getOption('key-size')->getDefault());
        $this->assertFalse($definition->getOption('agree-tos')->getDefault());
    }

    public function testArgumentEmail(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('email'));
        $this->assertTrue($definition->getArgument('email')->isRequired());
        $this->assertEquals('账户邮箱地址', $definition->getArgument('email')->getDescription());
    }

    public function testOptionDirectoryUrl(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('directory-url'));
        $this->assertEquals('https://acme-staging-v02.api.letsencrypt.org/directory',
            $definition->getOption('directory-url')->getDefault());
    }

    public function testOptionKeySize(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('key-size'));
        $this->assertEquals('2048', $definition->getOption('key-size')->getDefault());
    }

    public function testOptionAgreeTos(): void
    {
        $command = self::getService(AcmeAccountRegisterCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('agree-tos'));
        $this->assertFalse($definition->getOption('agree-tos')->getDefault());
    }
}
