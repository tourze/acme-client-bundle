<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Command\Handler\OperationQueryHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(OperationQueryHandler::class)]
#[RunTestsInSeparateProcesses]
final class OperationQueryHandlerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试的初始化逻辑
    }

    private function createHandler(): OperationQueryHandler
    {
        return self::getService(OperationQueryHandler::class);
    }

    public function testSupportsWhenTypeIsOperation(): void
    {
        $options = ['type' => 'operation'];

        $this->assertTrue($this->createHandler()->supports($options));
    }

    public function testSupportsWhenTypeIsNotOperation(): void
    {
        $options = ['type' => 'exception'];

        $this->assertFalse($this->createHandler()->supports($options));
    }

    public function testHandleWithSuccessfulQuery(): void
    {
        /*
         * 使用具体类 SymfonyStyle 进行 mock：
         * 1. SymfonyStyle 是 Symfony Console 组件的标准输出类，没有对应的接口
         * 2. 测试控制台交互必须模拟用户输入和输出行为，使用 mock 是标准做法
         * 3. Symfony 官方测试文档推荐这种方式来测试控制台命令
         */
        $io = $this->createMock(SymfonyStyle::class);

        $options = [
            'type' => 'operation',
            'operation' => null,
            'entityType' => null,
            'entityId' => null,
            'level' => null,
            'sinceInput' => null,
            'limit' => 10,
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
            'type' => 'operation',
            'operation' => null,
            'entityType' => null,
            'entityId' => null,
            'level' => null,
            'sinceInput' => 'invalid-date',
            'limit' => 10,
        ];

        $result = $this->createHandler()->handle($io, $options);

        $this->assertEquals(Command::FAILURE, $result);
    }
}
