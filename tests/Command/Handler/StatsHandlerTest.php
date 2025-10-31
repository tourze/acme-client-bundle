<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Command\Handler\StatsHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(StatsHandler::class)]
#[RunTestsInSeparateProcesses]
final class StatsHandlerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    private function createHandler(): StatsHandler
    {
        return self::getService(StatsHandler::class);
    }

    public function testSupportsWhenShowStatsIsTrue(): void
    {
        $options = ['showStats' => true];

        $this->assertTrue($this->createHandler()->supports($options));
    }

    public function testSupportsWhenShowStatsIsFalse(): void
    {
        $options = ['showStats' => false];

        $this->assertFalse($this->createHandler()->supports($options));
    }

    public function testHandleWithExceptionTypeStats(): void
    {
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);

        $options = [
            'showStats' => true,
            'type' => 'exception',
            'sinceInput' => null,
        ];

        $result = $this->createHandler()->handle($io, $options);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    public function testHandleWithOperationTypeStats(): void
    {
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);

        $options = [
            'showStats' => true,
            'type' => 'operation',
            'sinceInput' => null,
        ];

        $result = $this->createHandler()->handle($io, $options);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    public function testHandleWithAbstractAcmeException(): void
    {
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('error')
        ;

        $options = [
            'showStats' => true,
            'type' => 'operation',
            'sinceInput' => 'invalid-date',
        ];

        $result = $this->createHandler()->handle($io, $options);

        $this->assertEquals(Command::FAILURE, $result);
    }
}
