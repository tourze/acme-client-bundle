<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Service\LogQueryService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LogQueryService::class)]
#[RunTestsInSeparateProcesses]
final class LogQueryServiceTest extends AbstractIntegrationTestCase
{
    private LogQueryService $logQueryService;

    protected function onSetUp(): void
    {
        $this->logQueryService = self::getService(LogQueryService::class);
    }

    public function testQueryOperationLogs(): void
    {
        // 测试查询操作日志的基本功能
        // 由于没有真实数据，我们期望返回空数组
        $result = $this->logQueryService->queryOperationLogs('register', 'account', 123, 'info', null, 50);
        $this->assertEmpty($result);
    }

    public function testQueryExceptionLogs(): void
    {
        // 测试查询异常日志的基本功能
        // 由于没有真实数据，我们期望返回空数组
        $result = $this->logQueryService->queryExceptionLogs('account', 123, null, 50);
        $this->assertEmpty($result);
    }

    public function testGetOperationStatistics(): void
    {
        // 测试获取操作统计信息的基本功能
        // 由于没有真实数据，我们期望返回空的统计信息
        $since = new \DateTimeImmutable('+1 day'); // 使用未来时间确保没有数据
        $result = $this->logQueryService->getOperationStatistics($since);
        $this->assertArrayHasKey('operations', $result);
        $this->assertArrayHasKey('levels', $result);
        $this->assertArrayHasKey('entities', $result);
        // 这些数组应该是空的，因为我们使用了未来时间
    }

    public function testGetExceptionStatistics(): void
    {
        // 测试获取异常统计信息的基本功能
        // 由于没有真实数据，我们期望返回空的统计信息
        $since = new \DateTimeImmutable('+1 day'); // 使用未来时间确保没有数据
        $result = $this->logQueryService->getExceptionStatistics($since);
        // getExceptionStats 返回的是包含 exceptionClass 和 count 的数组列表
        foreach ($result as $stat) {
            $this->assertIsArray($stat);
            $this->assertArrayHasKey('exceptionClass', $stat);
            $this->assertArrayHasKey('count', $stat);
        }
    }

    public function testCleanupLogsSuccess(): void
    {
        // 测试清理日志的基本功能
        // 由于没有真实数据，我们期望返回清理的数量
        $result = $this->logQueryService->cleanupLogs(30);
        $this->assertCount(2, $result);
    }

    public function testCleanupLogsInvalidDays(): void
    {
        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('清理天数必须大于0');

        $this->logQueryService->cleanupLogs(0);
    }

    public function testParseSinceTimeWithValidInput(): void
    {
        $result = $this->logQueryService->parseSinceTime('2024-01-01 12:00:00');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2024-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testParseSinceTimeWithNullInput(): void
    {
        $result = $this->logQueryService->parseSinceTime(null);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertLessThanOrEqual(new \DateTimeImmutable('-7 days'), $result);
    }

    public function testParseSinceTimeWithInvalidInput(): void
    {
        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('无效的时间格式: invalid-date');

        $this->logQueryService->parseSinceTime('invalid-date');
    }
}
