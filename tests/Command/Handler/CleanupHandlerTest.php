<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Command\Handler\CleanupHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CleanupHandler::class)]
#[RunTestsInSeparateProcesses]
final class CleanupHandlerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试的初始化逻辑
    }

    private function createHandler(): CleanupHandler
    {
        return self::getService(CleanupHandler::class);
    }

    public function testSupportsWhenCleanupOptionIsSet(): void
    {
        $handler = $this->createHandler();
        $options = ['cleanup' => '30'];

        $this->assertTrue($handler->supports($options));
    }

    public function testSupportsWhenCleanupOptionIsNull(): void
    {
        $handler = $this->createHandler();
        $options = ['cleanup' => null];

        $this->assertFalse($handler->supports($options));
    }

    public function testHandleWithUserCancellation(): void
    {
        $handler = $this->createHandler();
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('section')
            ->with('ACME 日志清理')
        ;
        $io->expects($this->once())
            ->method('warning')
        ;
        $io->expects($this->once())
            ->method('confirm')
            ->willReturn(false)
        ;
        $io->expects($this->once())
            ->method('info')
            ->with('用户取消操作')
        ;

        $options = ['cleanup' => '30'];
        $result = $handler->handle($io, $options);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    public function testHandleWithSuccessfulCleanup(): void
    {
        $handler = $this->createHandler();
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('section')
        ;
        $io->expects($this->once())
            ->method('warning')
        ;
        $io->expects($this->once())
            ->method('confirm')
            ->willReturn(true)
        ;

        $options = ['cleanup' => '30'];
        $result = $handler->handle($io, $options);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    public function testHandleWithException(): void
    {
        $handler = $this->createHandler();
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('section')
        ;
        $io->expects($this->once())
            ->method('warning')
        ;
        $io->expects($this->once())
            ->method('confirm')
            ->willReturn(true)
        ;
        $io->expects($this->once())
            ->method('error')
        ;

        $options = ['cleanup' => '-1'];
        $result = $handler->handle($io, $options);

        $this->assertEquals(Command::FAILURE, $result);
    }
}
