<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * AcmeOperationLog 实体测试类
 *
 * @internal
 */
#[CoversClass(AcmeOperationLog::class)]
final class AcmeOperationLogTest extends AbstractEntityTestCase
{
    protected function createEntity(): AcmeOperationLog
    {
        return new AcmeOperationLog();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'level' => ['level', LogLevel::ERROR];
        yield 'operation' => ['operation', 'test_operation'];
        yield 'message' => ['message', 'Test message'];
        yield 'entityType' => ['entityType', 'Order'];
        yield 'entityId' => ['entityId', 123];
        yield 'context' => ['context', ['operation' => 'test']];
        yield 'httpUrl' => ['httpUrl', 'https://example.com'];
        yield 'httpMethod' => ['httpMethod', 'POST'];
        yield 'httpStatusCode' => ['httpStatusCode', 200];
        yield 'durationMs' => ['durationMs', 1000];
        yield 'success' => ['success', true];
    }

    public function testIsError(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->isError());

        $log->setLevel(LogLevel::ERROR);
        $this->assertTrue($log->isError());

        $log->setLevel(LogLevel::INFO);
        $this->assertFalse($log->isError());
    }

    public function testIsWarning(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->isWarning());

        $log->setLevel(LogLevel::WARNING);
        $this->assertTrue($log->isWarning());

        $log->setLevel(LogLevel::DEBUG);
        $this->assertFalse($log->isWarning());
    }

    public function testIsInfo(): void
    {
        $log = $this->createEntity();
        $this->assertTrue($log->isInfo());

        $log->setLevel(LogLevel::ERROR);
        $this->assertFalse($log->isInfo());

        $log->setLevel(LogLevel::INFO);
        $this->assertTrue($log->isInfo());
    }

    public function testIsDebug(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->isDebug());

        $log->setLevel(LogLevel::DEBUG);
        $this->assertTrue($log->isDebug());

        $log->setLevel(LogLevel::WARNING);
        $this->assertFalse($log->isDebug());
    }

    public function testHasRelatedEntityWithBothValues(): void
    {
        $log = $this->createEntity();
        $log->setEntityType('Order');
        $log->setEntityId(123);

        $this->assertTrue($log->hasRelatedEntity());
    }

    public function testHasRelatedEntityWithEntityTypeOnly(): void
    {
        $log = $this->createEntity();
        $log->setEntityType('Account');

        $this->assertFalse($log->hasRelatedEntity());
    }

    public function testHasRelatedEntityWithEntityIdOnly(): void
    {
        $log = $this->createEntity();
        $log->setEntityId(456);

        $this->assertFalse($log->hasRelatedEntity());
    }

    public function testHasRelatedEntityWithNeitherValue(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->hasRelatedEntity());
    }

    public function testHasHttpRequest(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->hasHttpRequest());

        $log->setHttpUrl('https://example.com');
        $this->assertTrue($log->hasHttpRequest());

        $log->setHttpUrl(null);
        $this->assertFalse($log->hasHttpRequest());
    }

    public function testHasHttpResponse(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->hasHttpResponse());

        $log->setHttpStatusCode(200);
        $this->assertTrue($log->hasHttpResponse());

        $log->setHttpStatusCode(null);
        $this->assertFalse($log->hasHttpResponse());
    }

    public function testGetFormattedDescriptionBasic(): void
    {
        $log = $this->createEntity();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('account_create');
        $log->setMessage('Account created successfully');

        $expected = '[info] account_create: Account created successfully';
        $this->assertSame($expected, $log->getFormattedDescription());
    }

    public function testGetFormattedDescriptionWithEntity(): void
    {
        $log = $this->createEntity();
        $log->setLevel(LogLevel::WARNING);
        $log->setOperation('order_retry');
        $log->setMessage('Order retry attempt');
        $log->setEntityType('Order');
        $log->setEntityId(123);

        $expected = '[warning] order_retry: Order retry attempt (Order#123)';
        $this->assertSame($expected, $log->getFormattedDescription());
    }

    public function testGetFormattedDescriptionWithDuration(): void
    {
        $log = $this->createEntity();
        $log->setLevel(LogLevel::DEBUG);
        $log->setOperation('challenge_validate');
        $log->setMessage('Challenge validation completed');
        $log->setDurationMs(2500);

        $expected = '[debug] challenge_validate: Challenge validation completed (2500ms)';
        $this->assertSame($expected, $log->getFormattedDescription());
    }

    public function testGetFormattedDescriptionWithEntityAndDuration(): void
    {
        $log = $this->createEntity();
        $log->setLevel(LogLevel::ERROR);
        $log->setOperation('certificate_download');
        $log->setMessage('Certificate download failed');
        $log->setEntityType('Certificate');
        $log->setEntityId(789);
        $log->setDurationMs(5000);

        $expected = '[error] certificate_download: Certificate download failed (Certificate#789) (5000ms)';
        $this->assertSame($expected, $log->getFormattedDescription());
    }

    public function testAccountOperationBasic(): void
    {
        $log = AcmeOperationLog::accountOperation('create', 'Account created successfully');

        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('account_create', $log->getOperation());
        $this->assertSame('Account created successfully', $log->getMessage());
        $this->assertSame('Account', $log->getEntityType());
    }

    public function testAccountOperationWithIdAndDetails(): void
    {
        $entityId = 123;
        $details = ['email' => 'user@example.com'];

        $log = AcmeOperationLog::accountOperation('update', 'Account updated', $entityId, $details);

        $this->assertSame('account_update', $log->getOperation());
        $this->assertSame('Account updated', $log->getMessage());
        $this->assertSame('Account', $log->getEntityType());
        $this->assertSame($entityId, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function testOrderOperationBasic(): void
    {
        $log = AcmeOperationLog::orderOperation('create', 'Order created');

        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('order_create', $log->getOperation());
        $this->assertSame('Order created', $log->getMessage());
        $this->assertSame('Order', $log->getEntityType());
    }

    public function testOrderOperationWithIdAndDetails(): void
    {
        $entityId = 456;
        $details = ['domains' => ['example.com', 'www.example.com']];

        $log = AcmeOperationLog::orderOperation('validate', 'Order validation completed', $entityId, $details);

        $this->assertSame('order_validate', $log->getOperation());
        $this->assertSame('Order validation completed', $log->getMessage());
        $this->assertSame('Order', $log->getEntityType());
        $this->assertSame($entityId, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function testChallengeOperationBasic(): void
    {
        $log = AcmeOperationLog::challengeOperation('create', 'Challenge created');

        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('challenge_create', $log->getOperation());
        $this->assertSame('Challenge created', $log->getMessage());
        $this->assertSame('Challenge', $log->getEntityType());
    }

    public function testChallengeOperationWithIdAndDetails(): void
    {
        $entityId = 789;
        $details = ['type' => 'dns-01', 'domain' => 'example.com'];

        $log = AcmeOperationLog::challengeOperation('validate', 'Challenge validated', $entityId, $details);

        $this->assertSame('challenge_validate', $log->getOperation());
        $this->assertSame('Challenge validated', $log->getMessage());
        $this->assertSame('Challenge', $log->getEntityType());
        $this->assertSame($entityId, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function testCertificateOperationBasic(): void
    {
        $log = AcmeOperationLog::certificateOperation('create', 'Certificate created');

        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('certificate_create', $log->getOperation());
        $this->assertSame('Certificate created', $log->getMessage());
        $this->assertSame('Certificate', $log->getEntityType());
    }

    public function testCertificateOperationWithIdAndDetails(): void
    {
        $entityId = 999;
        $details = ['serial' => 'ABC123', 'domains' => ['example.com']];

        $log = AcmeOperationLog::certificateOperation('install', 'Certificate installed', $entityId, $details);

        $this->assertSame('certificate_install', $log->getOperation());
        $this->assertSame('Certificate installed', $log->getMessage());
        $this->assertSame('Certificate', $log->getEntityType());
        $this->assertSame($entityId, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function testToString(): void
    {
        $log = $this->createEntity();
        $log->setOperation('test_operation');

        $expected = 'Log #0: test_operation';
        $this->assertSame($expected, (string) $log);
    }

    public function testStringableInterface(): void
    {
        $log = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $log);
    }

    public function testFluentInterfaceChaining(): void
    {
        $log = $this->createEntity();
        $level = LogLevel::WARNING;
        $operation = 'test_operation';
        $message = 'Test message';
        $entityType = 'TestEntity';
        $entityId = 123;
        $context = ['key' => 'value'];
        $httpUrl = 'https://example.com';
        $httpMethod = 'POST';
        $httpStatusCode = 200;
        $durationMs = 1000;

        $log->setLevel($level);
        $log->setOperation($operation);
        $log->setMessage($message);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setContext($context);
        $log->setHttpUrl($httpUrl);
        $log->setHttpMethod($httpMethod);
        $log->setHttpStatusCode($httpStatusCode);
        $log->setDurationMs($durationMs);
        $log->setSuccess(false);
        $result = $log;

        $this->assertSame($log, $result);
        $this->assertSame($level, $log->getLevel());
        $this->assertSame($operation, $log->getOperation());
        $this->assertSame($message, $log->getMessage());
        $this->assertSame($entityType, $log->getEntityType());
        $this->assertSame($entityId, $log->getEntityId());
        $this->assertSame($context, $log->getContext());
        $this->assertSame($httpUrl, $log->getHttpUrl());
        $this->assertSame($httpMethod, $log->getHttpMethod());
        $this->assertSame($httpStatusCode, $log->getHttpStatusCode());
        $this->assertSame($durationMs, $log->getDurationMs());
        $this->assertFalse($log->isSuccess());
    }

    public function testBusinessScenarioAccountRegistration(): void
    {
        $log = AcmeOperationLog::accountOperation('register', 'New ACME account registered', 123, [
            'email' => 'user@example.com',
            'terms_agreed' => true,
            'key_algorithm' => 'RSA-2048',
        ]);

        $this->assertSame('account_register', $log->getOperation());
        $this->assertStringContainsString('registered', $log->getMessage());
        $this->assertSame('Account', $log->getEntityType());
        $this->assertSame(123, $log->getEntityId());
        $this->assertTrue($log->isSuccess());
        $context = $log->getContext();
        $this->assertNotNull($context, 'Context should not be null');
        $this->assertArrayHasKey('email', $context);
    }

    public function testBusinessScenarioChallengeValidation(): void
    {
        $log = AcmeOperationLog::challengeOperation('validate', 'DNS challenge validation', 456, [
            'type' => 'dns-01',
            'domain' => 'example.com',
            'validation_time' => 2500,
        ]);

        $this->assertSame('challenge_validate', $log->getOperation());
        $this->assertStringContainsString('validation', $log->getMessage());
        $this->assertSame('Challenge', $log->getEntityType());
        $this->assertSame(456, $log->getEntityId());
        // 静态工厂方法不会自动设置持续时间，需要手动设置
        $this->assertNull($log->getDurationMs());
    }

    public function testBusinessScenarioCertificateIssuance(): void
    {
        $log = AcmeOperationLog::certificateOperation('issue', 'SSL certificate issued successfully', 789, [
            'serial' => 'ABC123DEF456',
            'domains' => ['example.com', 'www.example.com'],
            'validity_days' => 90,
        ]);

        $this->assertSame('certificate_issue', $log->getOperation());
        $this->assertStringContainsString('issued', $log->getMessage());
        $this->assertSame('Certificate', $log->getEntityType());
        $this->assertSame(789, $log->getEntityId());
        $this->assertTrue($log->isSuccess());
        $context = $log->getContext();
        $this->assertNotNull($context, 'Context should not be null');
        $this->assertArrayHasKey('domains', $context);
        $this->assertIsArray($context['domains']);
    }

    public function testBusinessScenarioOperationFailure(): void
    {
        $log = AcmeOperationLog::orderOperation('create', 'Order creation failed due to rate limit');
        $log->setLevel(LogLevel::ERROR);
        $log->setSuccess(false);
        $log->setHttpStatusCode(429);
        $log->setContext(['retry_after' => 3600]);

        $this->assertTrue($log->isError());
        $this->assertFalse($log->isSuccess());
        $this->assertSame(429, $log->getHttpStatusCode());
        $this->assertStringContainsString('rate limit', $log->getMessage());
    }

    public function testEdgeCasesZeroEntityId(): void
    {
        $log = $this->createEntity();
        $log->setEntityType('Test');
        $log->setEntityId(0);

        $this->assertTrue($log->hasRelatedEntity());
    }

    public function testEdgeCasesZeroDuration(): void
    {
        $log = $this->createEntity();
        $log->setOperation('fast_operation');
        $log->setMessage('Very fast operation');
        $log->setDurationMs(0);

        $description = $log->getFormattedDescription();
        $this->assertStringContainsString('(0ms)', $description);
    }

    public function testEdgeCasesLongOperation(): void
    {
        $longOperation = str_repeat('very_long_operation_name_', 10);
        $log = $this->createEntity();
        $log->setOperation($longOperation);

        $this->assertSame($longOperation, $log->getOperation());
    }

    public function testEdgeCasesComplexContext(): void
    {
        $complexContext = [
            'nested' => [
                'array' => ['value1', 'value2'],
                'object' => ['key' => 'value'],
            ],
            'numbers' => [1, 2, 3],
            'boolean' => true,
            'null' => null,
            'float' => 3.14,
        ];

        $log = $this->createEntity();
        $log->setContext($complexContext);
        $this->assertSame($complexContext, $log->getContext());
    }

    public function testLogLevelTransitions(): void
    {
        $log = $this->createEntity();
        // 从 INFO 开始
        $this->assertTrue($log->isInfo());
        $this->assertFalse($log->isDebug());
        $this->assertFalse($log->isWarning());
        $this->assertFalse($log->isError());

        // 切换到 DEBUG
        $log->setLevel(LogLevel::DEBUG);
        $this->assertFalse($log->isInfo());
        $this->assertTrue($log->isDebug());
        $this->assertFalse($log->isWarning());
        $this->assertFalse($log->isError());

        // 切换到 WARNING
        $log->setLevel(LogLevel::WARNING);
        $this->assertFalse($log->isInfo());
        $this->assertFalse($log->isDebug());
        $this->assertTrue($log->isWarning());
        $this->assertFalse($log->isError());

        // 切换到 ERROR
        $log->setLevel(LogLevel::ERROR);
        $this->assertFalse($log->isInfo());
        $this->assertFalse($log->isDebug());
        $this->assertFalse($log->isWarning());
        $this->assertTrue($log->isError());
    }

    public function testHttpStatusCodeMapping(): void
    {
        $statusCodes = [200, 201, 400, 401, 403, 404, 429, 500, 502, 503];

        $log = $this->createEntity();
        foreach ($statusCodes as $code) {
            $log->setHttpStatusCode($code);
            $this->assertSame($code, $log->getHttpStatusCode());
            $this->assertTrue($log->hasHttpResponse());
        }
    }

    public function testHttpMethodVariations(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        $log = $this->createEntity();
        foreach ($methods as $method) {
            $log->setHttpMethod($method);
            $this->assertSame($method, $log->getHttpMethod());
        }
    }

    public function testStaticFactoryMethodsConsistency(): void
    {
        $operations = ['create', 'update', 'delete', 'validate'];
        $message = 'Test operation';
        $entityId = 123;
        $context = ['test' => true];

        // 测试所有静态工厂方法的一致性
        $accountLog = AcmeOperationLog::accountOperation($operations[0], $message, $entityId, $context);
        $orderLog = AcmeOperationLog::orderOperation($operations[1], $message, $entityId, $context);
        $challengeLog = AcmeOperationLog::challengeOperation($operations[2], $message, $entityId, $context);
        $certificateLog = AcmeOperationLog::certificateOperation($operations[3], $message, $entityId, $context);

        // 验证所有日志都有正确的实体类型
        $this->assertSame('Account', $accountLog->getEntityType());
        $this->assertSame('Order', $orderLog->getEntityType());
        $this->assertSame('Challenge', $challengeLog->getEntityType());
        $this->assertSame('Certificate', $certificateLog->getEntityType());

        // 验证所有日志都有正确的操作前缀
        $this->assertStringStartsWith('account_', $accountLog->getOperation());
        $this->assertStringStartsWith('order_', $orderLog->getOperation());
        $this->assertStringStartsWith('challenge_', $challengeLog->getOperation());
        $this->assertStringStartsWith('certificate_', $certificateLog->getOperation());
    }
}
