<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Service\AcmeExceptionService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeExceptionService::class)]
#[RunTestsInSeparateProcesses]
final class AcmeExceptionServiceTest extends AbstractIntegrationTestCase
{
    private AcmeExceptionService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(AcmeExceptionService::class);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(AcmeExceptionService::class, $this->service);
    }

    public function testLogException(): void
    {
        $exception = new \RuntimeException('Test exception', 500);

        $result = $this->service->logException(
            $exception,
            'TestEntity',
            123,
            ['context' => 'test']
        );

        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $this->assertEquals('RuntimeException', $result->getExceptionClass());
        $this->assertEquals('Test exception', $result->getMessage());
        $this->assertEquals(500, $result->getCode());
        $this->assertEquals('TestEntity', $result->getEntityType());
        $this->assertEquals(123, $result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogExceptionWithDefaults(): void
    {
        $exception = new \Exception('Simple exception');

        $result = $this->service->logException($exception);

        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $this->assertEquals('Exception', $result->getExceptionClass());
        $this->assertEquals('Simple exception', $result->getMessage());
        $this->assertEquals(0, $result->getCode());
        $this->assertNull($result->getEntityType());
        $this->assertNull($result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testFindExceptionLogs(): void
    {
        // 先创建一些测试数据
        $this->service->logException(
            new \RuntimeException('Test exception 1', 500),
            'TestEntity',
            123,
            ['test' => 'data1']
        );

        $this->service->logException(
            new \InvalidArgumentException('Test exception 2', 400),
            'TestEntity',
            456,
            ['test' => 'data2']
        );

        $result = $this->service->findExceptionLogs(
            'RuntimeException',
            'TestEntity',
            123,
            50
        );
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertInstanceOf(AcmeExceptionLog::class, $result[0]);
        $this->assertEquals('RuntimeException', $result[0]->getExceptionClass());
    }

    public function testFindExceptionLogsWithDefaults(): void
    {
        // 先创建一些测试数据
        $this->service->logException(new \Exception('Test 1'));
        $this->service->logException(new \Exception('Test 2'));

        $result = $this->service->findExceptionLogs();
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function testCleanupOldExceptions(): void
    {
        // 先创建一些测试数据
        $this->service->logException(new \Exception('Old exception'));

        $result = $this->service->cleanupOldExceptions(30);

        // 返回类型已由方法签名保证为 int
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCleanupOldExceptionsWithCustomDays(): void
    {
        // 先创建一些测试数据
        $this->service->logException(new \Exception('Old exception'));

        $result = $this->service->cleanupOldExceptions(7);

        // 返回类型已由方法签名保证为 int
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testBusinessScenarioMultipleExceptions(): void
    {
        // 模拟不同类型的异常
        $log1 = $this->service->logException(
            new \RuntimeException('Runtime error'),
            'Account',
            123
        );

        $log2 = $this->service->logException(
            new \InvalidArgumentException('Invalid argument'),
            'Order',
            456
        );

        $log3 = $this->service->logException(
            new \LogicException('Logic error'),
            'Certificate',
            789
        );

        $this->assertEquals('RuntimeException', $log1->getExceptionClass());
        $this->assertEquals('InvalidArgumentException', $log2->getExceptionClass());
        $this->assertEquals('LogicException', $log3->getExceptionClass());

        // 验证所有实体都已持久化
        $this->assertEntityPersisted($log1);
        $this->assertEntityPersisted($log2);
        $this->assertEntityPersisted($log3);
    }

    public function testBusinessScenarioExceptionWithTrace(): void
    {
        $exception = new \RuntimeException('Test exception with trace', 500);

        $result = $this->service->logException(
            $exception,
            'TestEntity',
            123,
            ['trace_context' => 'full_trace']
        );

        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $stackTrace = $result->getStackTrace();
        $this->assertNotEmpty($stackTrace);
        $this->assertIsString($stackTrace);
        $this->assertStringContainsString('AcmeExceptionServiceTest', $stackTrace);

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testEdgeCasesEmptyException(): void
    {
        $exception = new \Exception('');

        $result = $this->service->logException($exception);

        $this->assertEquals('Exception', $result->getExceptionClass());
        $this->assertEquals('', $result->getMessage());
        $this->assertEquals(0, $result->getCode());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testEdgeCasesLargeContext(): void
    {
        $largeContext = array_fill_keys(array_map(static fn (int $i): string => "key_{$i}", range(0, 999)), 'large_data');
        $exception = new \RuntimeException('Large context exception', 500);

        $result = $this->service->logException(
            $exception,
            'LargeEntity',
            999999,
            $largeContext
        );

        $this->assertEquals('RuntimeException', $result->getExceptionClass());
        $this->assertEquals('Large context exception', $result->getMessage());
        $this->assertEquals($largeContext, $result->getContext());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testFindExceptions(): void
    {
        // 创建测试数据
        $exception1 = $this->service->logException(
            new \RuntimeException('Test exception 1', 500),
            'TestEntity',
            123,
            ['test' => 'data1']
        );

        $exception2 = $this->service->logException(
            new \InvalidArgumentException('Test exception 2', 400),
            'TestEntity',
            456,
            ['test' => 'data2']
        );

        $exception3 = $this->service->logException(
            new \LogicException('Test exception 3', 300),
            'OtherEntity',
            789,
            ['test' => 'data3']
        );

        // 测试按异常类查询
        $result = $this->service->findExceptions('RuntimeException');
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('RuntimeException', $result[0]->getExceptionClass());

        // 测试按实体类型查询
        $result = $this->service->findExceptions(null, 'TestEntity');
        $this->assertGreaterThanOrEqual(2, count($result));

        // 测试按实体ID查询
        $result = $this->service->findExceptions(null, null, 123);
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals(123, $result[0]->getEntityId());

        // 测试组合条件查询
        $result = $this->service->findExceptions('RuntimeException', 'TestEntity', 123);
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('RuntimeException', $result[0]->getExceptionClass());
        $this->assertEquals('TestEntity', $result[0]->getEntityType());
        $this->assertEquals(123, $result[0]->getEntityId());
    }

    public function testFindExceptionsWithSince(): void
    {
        // 创建一些测试数据
        $this->service->logException(new \RuntimeException('Old exception'));

        // 等待一小段时间确保时间差异
        usleep(10000); // 10ms

        // 创建异常后再设置 since 时间
        $this->service->logException(new \RuntimeException('New exception'));

        // 稍等片刻然后设置 since 时间为更早的时间
        usleep(5000);
        $since = new \DateTimeImmutable('-2 seconds');

        // 查询 since 时间之后的异常
        $result = $this->service->findExceptions(null, null, null, $since);
        $this->assertGreaterThanOrEqual(1, count($result));

        // 验证返回的异常都是在 since 时间之后的或等于 since 时间
        // 使用更宽松的时间比较来避免微秒精度问题
        foreach ($result as $exception) {
            $this->assertGreaterThanOrEqual($since->getTimestamp(), $exception->getOccurredTime()->getTimestamp());
        }
    }

    public function testFindExceptionsWithLimit(): void
    {
        // 创建多个异常
        for ($i = 0; $i < 10; ++$i) {
            $this->service->logException(new \RuntimeException("Exception {$i}"));
        }

        // 测试限制返回数量
        $result = $this->service->findExceptions(null, null, null, null, 5);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function testFindExceptionsEmptyResult(): void
    {
        // 查询不存在的异常类
        $result = $this->service->findExceptions('NonExistentException');
        $this->assertEmpty($result);
    }

    public function testFindExceptionsOrderByOccurredAtDesc(): void
    {
        // 创建多个异常，确保有时间差
        $exception1 = $this->service->logException(new \RuntimeException('First'));
        sleep(1); // 1 second
        $exception2 = $this->service->logException(new \RuntimeException('Second'));
        sleep(1); // 1 second
        $exception3 = $this->service->logException(new \RuntimeException('Third'));

        // 获取所有异常
        $result = $this->service->findExceptions('RuntimeException');

        // 验证按时间降序排列（最新的在前）
        $this->assertGreaterThanOrEqual(3, count($result));

        // 由于我们使用了 sleep(1)，最新的应该是 exception3
        $latestExceptions = array_slice($result, 0, 3);
        $this->assertEquals('Third', $latestExceptions[0]->getMessage());
        $this->assertEquals('Second', $latestExceptions[1]->getMessage());
        $this->assertEquals('First', $latestExceptions[2]->getMessage());
    }
}
