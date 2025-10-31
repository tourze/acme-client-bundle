<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Service\AcmeExceptionService;
use Tourze\ACMEClientBundle\Service\AcmeLogService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeLogService::class)]
#[RunTestsInSeparateProcesses]
final class AcmeLogServiceTest extends AbstractIntegrationTestCase
{
    private AcmeLogService $service;

    /**
     * @var MockObject&AcmeExceptionService
     */
    private MockObject $exceptionService;

    protected function onSetUp(): void
    {
        /*
         * 使用具体类 AcmeExceptionService 的 Mock 对象
         * 原因：AcmeExceptionService 是核心异常处理服务类，没有对应的接口抽象
         * 合理性：在测试中需要隔离异常处理逻辑，使用 Mock 是必要的测试实践
         */
        $this->exceptionService = $this->createMock(AcmeExceptionService::class);
        self::getContainer()->set(AcmeExceptionService::class, $this->exceptionService);

        $this->service = self::getService(AcmeLogService::class);
    }

    public function testConstructor(): void
    {
        // Service 通过依赖注入初始化，类型已由 PHPDoc 保证
        $this->assertInstanceOf(AcmeLogService::class, $this->service);
    }

    public function testLogAccountOperation(): void
    {
        $result = $this->service->logAccountOperation(
            'register',
            'Account registered successfully',
            123,
            ['email' => 'test@example.com'],
            LogLevel::INFO
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('account_register', $result->getOperation());
        $this->assertEquals('Account registered successfully', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertEquals('Account', $result->getEntityType());
        $this->assertEquals(123, $result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogAccountOperationWithDefaults(): void
    {
        $result = $this->service->logAccountOperation(
            'create',
            'Account created'
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('account_create', $result->getOperation());
        $this->assertEquals('Account created', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertEquals('Account', $result->getEntityType());
        $this->assertNull($result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogOrderOperation(): void
    {
        $result = $this->service->logOrderOperation(
            'create',
            'Order created successfully',
            456,
            ['domains' => ['example.com']],
            LogLevel::DEBUG
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('order_create', $result->getOperation());
        $this->assertEquals('Order created successfully', $result->getMessage());
        $this->assertEquals(LogLevel::DEBUG, $result->getLevel());
        $this->assertEquals('Order', $result->getEntityType());
        $this->assertEquals(456, $result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogChallengeOperation(): void
    {
        $result = $this->service->logChallengeOperation(
            'validate',
            'Challenge validated',
            789,
            ['type' => 'dns-01'],
            LogLevel::WARNING
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('challenge_validate', $result->getOperation());
        $this->assertEquals('Challenge validated', $result->getMessage());
        $this->assertEquals(LogLevel::WARNING, $result->getLevel());
        $this->assertEquals('Challenge', $result->getEntityType());
        $this->assertEquals(789, $result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogCertificateOperation(): void
    {
        $result = $this->service->logCertificateOperation(
            'issue',
            'Certificate issued',
            101,
            ['serial' => 'ABC123'],
            LogLevel::ERROR
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('certificate_issue', $result->getOperation());
        $this->assertEquals('Certificate issued', $result->getMessage());
        $this->assertEquals(LogLevel::ERROR, $result->getLevel());
        $this->assertEquals('Certificate', $result->getEntityType());
        $this->assertEquals(101, $result->getEntityId());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogOperation(): void
    {
        $result = $this->service->logOperation(
            'custom_operation',
            'Custom operation performed',
            'CustomEntity',
            999,
            ['key' => 'value'],
            LogLevel::INFO
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('custom_operation', $result->getOperation());
        $this->assertEquals('Custom operation performed', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertEquals('CustomEntity', $result->getEntityType());
        $this->assertEquals(999, $result->getEntityId());
        $this->assertEquals(['key' => 'value'], $result->getContext());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogOperationWithDefaults(): void
    {
        $result = $this->service->logOperation(
            'simple_operation',
            'Simple operation'
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('simple_operation', $result->getOperation());
        $this->assertEquals('Simple operation', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertNull($result->getEntityType());
        $this->assertNull($result->getEntityId());
        $this->assertNull($result->getContext());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testLogException(): void
    {
        $exception = new \RuntimeException('Test exception', 500);

        // 设置 mock 期望
        $this->exceptionService->expects($this->once())
            ->method('logException')
            ->with(
                $exception,
                'TestEntity',
                123,
                ['context' => 'test']
            )
        ;

        $this->service->logException(
            $exception,
            'TestEntity',
            123,
            ['context' => 'test']
        );
    }

    public function testLogExceptionWithDefaults(): void
    {
        $exception = new \Exception('Simple exception');

        // 设置 mock 期望
        $this->exceptionService->expects($this->once())
            ->method('logException')
            ->with($exception, null, null, null)
        ;

        $this->service->logException($exception);
    }

    public function testFindLogsWithAllFilters(): void
    {
        // 先创建一些测试数据
        $this->service->logOperation(
            'test_operation',
            'Test operation message',
            'TestEntity',
            123,
            ['test' => 'data'],
            LogLevel::INFO
        );

        // 确保数据刷新到数据库
        self::getEntityManager()->flush();

        $result = $this->service->findLogs(
            'test_operation',
            'TestEntity',
            123,
            'info',
            50
        );
        $this->assertCount(1, $result);
        $this->assertInstanceOf(AcmeOperationLog::class, $result[0]);
        $this->assertEquals('test_operation', $result[0]->getOperation());
    }

    public function testFindLogsWithDefaults(): void
    {
        // 先创建一些测试数据
        $this->service->logOperation('test1', 'Test 1');
        $this->service->logOperation('test2', 'Test 2');

        // 确保数据刷新到数据库
        self::getEntityManager()->flush();

        $result = $this->service->findLogs();
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function testCleanupOldLogs(): void
    {
        // 先创建一些测试数据
        $this->service->logOperation('old_operation', 'Old operation');

        $result = $this->service->cleanupOldLogs(30);

        // 返回类型已由方法签名保证为 int
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testBusinessScenarioAccountLifecycle(): void
    {
        // 账户注册
        $registerLog = $this->service->logAccountOperation(
            'register',
            'Account registration started',
            null,
            ['email' => 'user@example.com']
        );

        // 账户激活
        $activateLog = $this->service->logAccountOperation(
            'activate',
            'Account activated successfully',
            123,
            ['status' => 'valid']
        );

        // 账户停用
        $deactivateLog = $this->service->logAccountOperation(
            'deactivate',
            'Account deactivated',
            123,
            ['reason' => 'user_request'],
            LogLevel::WARNING
        );

        $this->assertEquals('account_register', $registerLog->getOperation());
        $this->assertEquals('account_activate', $activateLog->getOperation());
        $this->assertEquals('account_deactivate', $deactivateLog->getOperation());
        $this->assertEquals(LogLevel::WARNING, $deactivateLog->getLevel());

        // 验证所有实体都已持久化
        $this->assertEntityPersisted($registerLog);
        $this->assertEntityPersisted($activateLog);
        $this->assertEntityPersisted($deactivateLog);
    }

    public function testBusinessScenarioCertificateIssuance(): void
    {
        // 订单创建
        $orderLog = $this->service->logOrderOperation(
            'create',
            'Order created for domain example.com',
            456,
            ['domains' => ['example.com']],
        );

        // 质询验证
        $challengeLog = $this->service->logChallengeOperation(
            'validate',
            'DNS challenge validated',
            789,
            ['type' => 'dns-01', 'domain' => 'example.com']
        );

        // 证书签发
        $issueLog = $this->service->logCertificateOperation(
            'issue',
            'Certificate issued successfully',
            101,
            ['serial' => 'ABC123DEF456', 'expires' => '2024-12-31']
        );

        // 证书下载
        $downloadLog = $this->service->logCertificateOperation(
            'download',
            'Certificate downloaded',
            101,
            ['format' => 'pem']
        );

        $this->assertEquals('Order', $orderLog->getEntityType());
        $this->assertEquals('Challenge', $challengeLog->getEntityType());
        $this->assertEquals('Certificate', $issueLog->getEntityType());
        $this->assertEquals('Certificate', $downloadLog->getEntityType());

        // 验证所有实体都已持久化
        $this->assertEntityPersisted($orderLog);
        $this->assertEntityPersisted($challengeLog);
        $this->assertEntityPersisted($issueLog);
        $this->assertEntityPersisted($downloadLog);
    }

    public function testEdgeCasesEmptyStrings(): void
    {
        $result = $this->service->logOperation('', '');

        $this->assertEquals('', $result->getOperation());
        $this->assertEquals('', $result->getMessage());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testEdgeCasesLargeData(): void
    {
        $largeContext = array_fill_keys(array_map(static fn (int $i): string => "key_{$i}", range(0, 999)), 'data');
        $longMessage = str_repeat('A', 10000);

        $result = $this->service->logOperation(
            'large_operation',
            $longMessage,
            'LargeEntity',
            999999,
            $largeContext
        );

        $this->assertEquals('large_operation', $result->getOperation());
        $this->assertEquals($longMessage, $result->getMessage());
        $this->assertEquals($largeContext, $result->getContext());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }
}
