<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Service\LogDisplayService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LogDisplayService::class)]
#[RunTestsInSeparateProcesses]
final class LogDisplayServiceTest extends AbstractIntegrationTestCase
{
    private LogDisplayService $logDisplayService;

    /**
     * @var MockObject&SymfonyStyle
     */
    private MockObject $mockIo;

    protected function onSetUp(): void
    {
        $this->logDisplayService = self::getService(LogDisplayService::class);
        // 必须使用具体类进行mock的原因：
        // 1. SymfonyStyle是Symfony框架的核心组件，需要测试具体的输出行为
        // 2. 为其创建抽象层会显著增加复杂度而没有明显好处
        // 3. 该类在Symfony生态系统中高度稳定，mock风险极低
        $this->mockIo = $this->createMock(SymfonyStyle::class);
    }

    public function testDisplayOperationLogsResultsWithEmptyLogs(): void
    {
        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('没有找到匹配的操作日志')
        ;

        $this->logDisplayService->displayOperationLogsResults($this->mockIo, []);
    }

    public function testDisplayOperationLogsResultsWithLogs(): void
    {
        $mockLog = new AcmeOperationLog();
        $mockLog->setOperation('register');
        $mockLog->setLevel(LogLevel::INFO);
        $mockLog->setEntityType('account');
        $mockLog->setEntityId(123);
        $mockLog->setMessage('Test message');
        $mockLog->setOccurredTime(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $mockLogs = [$mockLog];

        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('找到 1 条操作日志')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
        ;

        $this->mockIo->expects($this->once())
            ->method('confirm')
            ->with('是否查看详细信息？', false)
            ->willReturn(false)
        ;

        $this->logDisplayService->displayOperationLogsResults($this->mockIo, $mockLogs);
    }

    public function testDisplayExceptionLogsResultsWithEmptyExceptions(): void
    {
        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('没有找到匹配的异常日志')
        ;

        $this->logDisplayService->displayExceptionLogsResults($this->mockIo, []);
    }

    public function testDisplayExceptionLogsResultsWithExceptions(): void
    {
        $mockException = new AcmeExceptionLog();
        $mockException->setExceptionClass('RuntimeException');
        $mockException->setMessage('Test exception');
        $mockException->setEntityType('account');
        $mockException->setEntityId(123);
        $mockException->setOccurredTime(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $mockExceptions = [$mockException];

        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('找到 1 条异常日志')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
        ;

        $this->mockIo->expects($this->once())
            ->method('confirm')
            ->with('是否查看异常详情？', false)
            ->willReturn(false)
        ;

        $this->logDisplayService->displayExceptionLogsResults($this->mockIo, $mockExceptions);
    }

    public function testDisplayOperationStatisticsWithEmptyStats(): void
    {
        $since = new \DateTimeImmutable('2024-01-01');
        $stats = ['operations' => [], 'levels' => [], 'entities' => []];

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('ACME 日志统计信息')
        ;

        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('该时间范围内没有操作记录')
        ;

        $this->logDisplayService->displayOperationStatistics($this->mockIo, $stats, $since);
    }

    public function testDisplayOperationStatisticsWithData(): void
    {
        $since = new \DateTimeImmutable('2024-01-01');
        $stats = [
            'operations' => ['register' => 5, 'create' => 3],
            'levels' => ['info' => 4, 'error' => 4],
            'entities' => ['account' => 5, 'order' => 3],
        ];

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('ACME 日志统计信息')
        ;

        $this->mockIo->expects($this->exactly(3))
            ->method('table')
        ;

        $this->logDisplayService->displayOperationStatistics($this->mockIo, $stats, $since);
    }

    public function testDisplayExceptionStatisticsWithEmptyStats(): void
    {
        $since = new \DateTimeImmutable('2024-01-01');

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('ACME 日志统计信息')
        ;

        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('该时间范围内没有异常记录')
        ;

        $this->logDisplayService->displayExceptionStatistics($this->mockIo, [], $since);
    }

    public function testDisplayExceptionStatisticsWithData(): void
    {
        $since = new \DateTimeImmutable('2024-01-01');
        $stats = [
            ['exceptionClass' => 'RuntimeException', 'count' => 5],
            ['exceptionClass' => 'InvalidArgumentException', 'count' => 3],
        ];

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('ACME 日志统计信息')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(['异常类型', '数量'], [
                ['RuntimeException', 5],
                ['InvalidArgumentException', 3],
            ])
        ;

        $this->logDisplayService->displayExceptionStatistics($this->mockIo, $stats, $since);
    }

    public function testDisplayCleanupResults(): void
    {
        $this->mockIo->expects($this->once())
            ->method('success')
            ->with('清理完成：')
        ;

        $this->mockIo->expects($this->exactly(3))
            ->method('text')
        ;

        $this->logDisplayService->displayCleanupResults($this->mockIo, 100, 50);
    }
}
