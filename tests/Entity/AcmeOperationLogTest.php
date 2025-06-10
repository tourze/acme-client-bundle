<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;

/**
 * AcmeOperationLog 实体测试类
 */
class AcmeOperationLogTest extends TestCase
{
    private AcmeOperationLog $operationLog;

    protected function setUp(): void
    {
        $this->operationLog = new AcmeOperationLog();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->operationLog->getId());
        $this->assertSame(LogLevel::INFO, $this->operationLog->getLevel());
        $this->assertNull($this->operationLog->getEntityType());
        $this->assertNull($this->operationLog->getEntityId());
        $this->assertNull($this->operationLog->getContext());
        $this->assertNull($this->operationLog->getHttpUrl());
        $this->assertNull($this->operationLog->getHttpMethod());
        $this->assertNull($this->operationLog->getHttpStatusCode());
        $this->assertNull($this->operationLog->getDurationMs());
        $this->assertTrue($this->operationLog->isSuccess());
    }

    public function test_level_getterSetter(): void
    {
        $this->assertSame(LogLevel::INFO, $this->operationLog->getLevel());

        $result = $this->operationLog->setLevel(LogLevel::ERROR);
        $this->assertSame($this->operationLog, $result);
        $this->assertSame(LogLevel::ERROR, $this->operationLog->getLevel());
    }

    public function test_operation_getterSetter(): void
    {
        $operation = 'account_create';
        $result = $this->operationLog->setOperation($operation);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($operation, $this->operationLog->getOperation());
    }

    public function test_message_getterSetter(): void
    {
        $message = 'Account created successfully';
        $result = $this->operationLog->setMessage($message);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($message, $this->operationLog->getMessage());
    }

    public function test_entityType_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getEntityType());

        $entityType = 'Account';
        $result = $this->operationLog->setEntityType($entityType);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($entityType, $this->operationLog->getEntityType());
    }

    public function test_entityType_setToNull(): void
    {
        $this->operationLog->setEntityType('Order');
        $this->operationLog->setEntityType(null);
        
        $this->assertNull($this->operationLog->getEntityType());
    }

    public function test_entityId_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getEntityId());

        $entityId = 123;
        $result = $this->operationLog->setEntityId($entityId);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($entityId, $this->operationLog->getEntityId());
    }

    public function test_entityId_setToNull(): void
    {
        $this->operationLog->setEntityId(456);
        $this->operationLog->setEntityId(null);
        
        $this->assertNull($this->operationLog->getEntityId());
    }

    public function test_context_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getContext());

        $context = [
            'domain' => 'example.com',
            'challenge_type' => 'dns-01',
            'attempt' => 1
        ];
        $result = $this->operationLog->setContext($context);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($context, $this->operationLog->getContext());
    }

    public function test_context_setToNull(): void
    {
        $this->operationLog->setContext(['test' => 'value']);
        $this->operationLog->setContext(null);
        
        $this->assertNull($this->operationLog->getContext());
    }

    public function test_httpUrl_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getHttpUrl());

        $httpUrl = 'https://acme-v02.api.letsencrypt.org/acme/new-account';
        $result = $this->operationLog->setHttpUrl($httpUrl);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($httpUrl, $this->operationLog->getHttpUrl());
    }

    public function test_httpUrl_setToNull(): void
    {
        $this->operationLog->setHttpUrl('https://example.com');
        $this->operationLog->setHttpUrl(null);
        
        $this->assertNull($this->operationLog->getHttpUrl());
    }

    public function test_httpMethod_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getHttpMethod());

        $httpMethod = 'POST';
        $result = $this->operationLog->setHttpMethod($httpMethod);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($httpMethod, $this->operationLog->getHttpMethod());
    }

    public function test_httpMethod_setToNull(): void
    {
        $this->operationLog->setHttpMethod('GET');
        $this->operationLog->setHttpMethod(null);
        
        $this->assertNull($this->operationLog->getHttpMethod());
    }

    public function test_httpStatusCode_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getHttpStatusCode());

        $statusCode = 201;
        $result = $this->operationLog->setHttpStatusCode($statusCode);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($statusCode, $this->operationLog->getHttpStatusCode());
    }

    public function test_httpStatusCode_setToNull(): void
    {
        $this->operationLog->setHttpStatusCode(200);
        $this->operationLog->setHttpStatusCode(null);
        
        $this->assertNull($this->operationLog->getHttpStatusCode());
    }

    public function test_durationMs_getterSetter(): void
    {
        $this->assertNull($this->operationLog->getDurationMs());

        $duration = 1500;
        $result = $this->operationLog->setDurationMs($duration);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($duration, $this->operationLog->getDurationMs());
    }

    public function test_durationMs_setToNull(): void
    {
        $this->operationLog->setDurationMs(2000);
        $this->operationLog->setDurationMs(null);
        
        $this->assertNull($this->operationLog->getDurationMs());
    }

    public function test_success_getterSetter(): void
    {
        $this->assertTrue($this->operationLog->isSuccess());

        $result = $this->operationLog->setSuccess(false);
        $this->assertSame($this->operationLog, $result);
        $this->assertFalse($this->operationLog->isSuccess());

        $this->operationLog->setSuccess(true);
        $this->assertTrue($this->operationLog->isSuccess());
    }

    public function test_isError(): void
    {
        $this->assertFalse($this->operationLog->isError());

        $this->operationLog->setLevel(LogLevel::ERROR);
        $this->assertTrue($this->operationLog->isError());

        $this->operationLog->setLevel(LogLevel::INFO);
        $this->assertFalse($this->operationLog->isError());
    }

    public function test_isWarning(): void
    {
        $this->assertFalse($this->operationLog->isWarning());

        $this->operationLog->setLevel(LogLevel::WARNING);
        $this->assertTrue($this->operationLog->isWarning());

        $this->operationLog->setLevel(LogLevel::DEBUG);
        $this->assertFalse($this->operationLog->isWarning());
    }

    public function test_isInfo(): void
    {
        $this->assertTrue($this->operationLog->isInfo());

        $this->operationLog->setLevel(LogLevel::ERROR);
        $this->assertFalse($this->operationLog->isInfo());

        $this->operationLog->setLevel(LogLevel::INFO);
        $this->assertTrue($this->operationLog->isInfo());
    }

    public function test_isDebug(): void
    {
        $this->assertFalse($this->operationLog->isDebug());

        $this->operationLog->setLevel(LogLevel::DEBUG);
        $this->assertTrue($this->operationLog->isDebug());

        $this->operationLog->setLevel(LogLevel::WARNING);
        $this->assertFalse($this->operationLog->isDebug());
    }

    public function test_hasRelatedEntity_withBothValues(): void
    {
        $this->operationLog
            ->setEntityType('Order')
            ->setEntityId(123);
        
        $this->assertTrue($this->operationLog->hasRelatedEntity());
    }

    public function test_hasRelatedEntity_withEntityTypeOnly(): void
    {
        $this->operationLog->setEntityType('Account');
        
        $this->assertFalse($this->operationLog->hasRelatedEntity());
    }

    public function test_hasRelatedEntity_withEntityIdOnly(): void
    {
        $this->operationLog->setEntityId(456);
        
        $this->assertFalse($this->operationLog->hasRelatedEntity());
    }

    public function test_hasRelatedEntity_withNeitherValue(): void
    {
        $this->assertFalse($this->operationLog->hasRelatedEntity());
    }

    public function test_hasHttpRequest(): void
    {
        $this->assertFalse($this->operationLog->hasHttpRequest());

        $this->operationLog->setHttpUrl('https://example.com');
        $this->assertTrue($this->operationLog->hasHttpRequest());

        $this->operationLog->setHttpUrl(null);
        $this->assertFalse($this->operationLog->hasHttpRequest());
    }

    public function test_hasHttpResponse(): void
    {
        $this->assertFalse($this->operationLog->hasHttpResponse());

        $this->operationLog->setHttpStatusCode(200);
        $this->assertTrue($this->operationLog->hasHttpResponse());

        $this->operationLog->setHttpStatusCode(null);
        $this->assertFalse($this->operationLog->hasHttpResponse());
    }

    public function test_getFormattedDescription_basic(): void
    {
        $this->operationLog
            ->setLevel(LogLevel::INFO)
            ->setOperation('account_create')
            ->setMessage('Account created successfully');
        
        $expected = '[info] account_create: Account created successfully';
        $this->assertSame($expected, $this->operationLog->getFormattedDescription());
    }

    public function test_getFormattedDescription_withEntity(): void
    {
        $this->operationLog
            ->setLevel(LogLevel::WARNING)
            ->setOperation('order_retry')
            ->setMessage('Order retry attempt')
            ->setEntityType('Order')
            ->setEntityId(123);
        
        $expected = '[warning] order_retry: Order retry attempt (Order#123)';
        $this->assertSame($expected, $this->operationLog->getFormattedDescription());
    }

    public function test_getFormattedDescription_withDuration(): void
    {
        $this->operationLog
            ->setLevel(LogLevel::DEBUG)
            ->setOperation('challenge_validate')
            ->setMessage('Challenge validation completed')
            ->setDurationMs(2500);
        
        $expected = '[debug] challenge_validate: Challenge validation completed (2500ms)';
        $this->assertSame($expected, $this->operationLog->getFormattedDescription());
    }

    public function test_getFormattedDescription_withEntityAndDuration(): void
    {
        $this->operationLog
            ->setLevel(LogLevel::ERROR)
            ->setOperation('certificate_download')
            ->setMessage('Certificate download failed')
            ->setEntityType('Certificate')
            ->setEntityId(789)
            ->setDurationMs(5000);
        
        $expected = '[error] certificate_download: Certificate download failed (Certificate#789) (5000ms)';
        $this->assertSame($expected, $this->operationLog->getFormattedDescription());
    }

    public function test_accountOperation_basic(): void
    {
        $log = AcmeOperationLog::accountOperation('create', 'Account created successfully');
        
        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('account_create', $log->getOperation());
        $this->assertSame('Account created successfully', $log->getMessage());
        $this->assertSame('Account', $log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
        $this->assertSame(LogLevel::INFO, $log->getLevel());
        $this->assertTrue($log->isSuccess());
    }

    public function test_accountOperation_withIdAndDetails(): void
    {
        $details = ['email' => 'test@example.com', 'key_type' => 'RSA'];
        $log = AcmeOperationLog::accountOperation('update', 'Account updated', 123, $details);
        
        $this->assertSame('account_update', $log->getOperation());
        $this->assertSame('Account updated', $log->getMessage());
        $this->assertSame('Account', $log->getEntityType());
        $this->assertSame(123, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function test_orderOperation_basic(): void
    {
        $log = AcmeOperationLog::orderOperation('submit', 'Order submitted to ACME server');
        
        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('order_submit', $log->getOperation());
        $this->assertSame('Order submitted to ACME server', $log->getMessage());
        $this->assertSame('Order', $log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
    }

    public function test_orderOperation_withIdAndDetails(): void
    {
        $details = ['domains' => ['example.com', 'www.example.com'], 'status' => 'pending'];
        $log = AcmeOperationLog::orderOperation('finalize', 'Order finalized', 456, $details);
        
        $this->assertSame('order_finalize', $log->getOperation());
        $this->assertSame('Order finalized', $log->getMessage());
        $this->assertSame('Order', $log->getEntityType());
        $this->assertSame(456, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function test_challengeOperation_basic(): void
    {
        $log = AcmeOperationLog::challengeOperation('validate', 'Challenge validation started');
        
        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('challenge_validate', $log->getOperation());
        $this->assertSame('Challenge validation started', $log->getMessage());
        $this->assertSame('Challenge', $log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
    }

    public function test_challengeOperation_withIdAndDetails(): void
    {
        $details = ['type' => 'dns-01', 'domain' => 'example.com', 'token' => 'abc123'];
        $log = AcmeOperationLog::challengeOperation('complete', 'Challenge completed', 789, $details);
        
        $this->assertSame('challenge_complete', $log->getOperation());
        $this->assertSame('Challenge completed', $log->getMessage());
        $this->assertSame('Challenge', $log->getEntityType());
        $this->assertSame(789, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function test_certificateOperation_basic(): void
    {
        $log = AcmeOperationLog::certificateOperation('download', 'Certificate downloaded');
        
        $this->assertInstanceOf(AcmeOperationLog::class, $log);
        $this->assertSame('certificate_download', $log->getOperation());
        $this->assertSame('Certificate downloaded', $log->getMessage());
        $this->assertSame('Certificate', $log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
    }

    public function test_certificateOperation_withIdAndDetails(): void
    {
        $details = ['serial' => 'ABC123', 'expires' => '2024-12-31', 'domains' => ['example.com']];
        $log = AcmeOperationLog::certificateOperation('install', 'Certificate installed', 999, $details);
        
        $this->assertSame('certificate_install', $log->getOperation());
        $this->assertSame('Certificate installed', $log->getMessage());
        $this->assertSame('Certificate', $log->getEntityType());
        $this->assertSame(999, $log->getEntityId());
        $this->assertSame($details, $log->getContext());
    }

    public function test_toString(): void
    {
        $this->operationLog->setOperation('test_operation');
        
        $expected = 'Log #0: test_operation';
        $this->assertSame($expected, (string) $this->operationLog);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->operationLog);
    }

    public function test_fluentInterface_chaining(): void
    {
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

        $result = $this->operationLog
            ->setLevel($level)
            ->setOperation($operation)
            ->setMessage($message)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setContext($context)
            ->setHttpUrl($httpUrl)
            ->setHttpMethod($httpMethod)
            ->setHttpStatusCode($httpStatusCode)
            ->setDurationMs($durationMs)
            ->setSuccess(false);

        $this->assertSame($this->operationLog, $result);
        $this->assertSame($level, $this->operationLog->getLevel());
        $this->assertSame($operation, $this->operationLog->getOperation());
        $this->assertSame($message, $this->operationLog->getMessage());
        $this->assertSame($entityType, $this->operationLog->getEntityType());
        $this->assertSame($entityId, $this->operationLog->getEntityId());
        $this->assertSame($context, $this->operationLog->getContext());
        $this->assertSame($httpUrl, $this->operationLog->getHttpUrl());
        $this->assertSame($httpMethod, $this->operationLog->getHttpMethod());
        $this->assertSame($httpStatusCode, $this->operationLog->getHttpStatusCode());
        $this->assertSame($durationMs, $this->operationLog->getDurationMs());
        $this->assertFalse($this->operationLog->isSuccess());
    }

    public function test_businessScenario_accountRegistration(): void
    {
        $log = AcmeOperationLog::accountOperation('register', 'New ACME account registered', 123, [
            'email' => 'user@example.com',
            'terms_agreed' => true,
            'key_algorithm' => 'RSA-2048'
        ]);

        $log->setLevel(LogLevel::INFO)
            ->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/new-account')
            ->setHttpMethod('POST')
            ->setHttpStatusCode(201)
            ->setDurationMs(850)
            ->setSuccess(true);

        $this->assertSame('account_register', $log->getOperation());
        $this->assertTrue($log->isInfo());
        $this->assertTrue($log->hasRelatedEntity());
        $this->assertTrue($log->hasHttpRequest());
        $this->assertTrue($log->hasHttpResponse());
        $this->assertTrue($log->isSuccess());
        $this->assertStringContainsString('Account#123', $log->getFormattedDescription());
    }

    public function test_businessScenario_challengeValidation(): void
    {
        $log = AcmeOperationLog::challengeOperation('dns_setup', 'DNS TXT record configured', 456, [
            'domain' => 'example.com',
            'record_name' => '_acme-challenge.example.com',
            'record_value' => 'abc123def456',
            'ttl' => 300
        ]);

        $log->setLevel(LogLevel::DEBUG)
            ->setDurationMs(2500)
            ->setSuccess(true);

        $this->assertSame('challenge_dns_setup', $log->getOperation());
        $this->assertTrue($log->isDebug());
        $this->assertSame('Challenge', $log->getEntityType());
        $this->assertArrayHasKey('domain', $log->getContext());
        $this->assertStringContainsString('2500ms', $log->getFormattedDescription());
    }

    public function test_businessScenario_certificateIssuance(): void
    {
        $log = AcmeOperationLog::certificateOperation('issue', 'Certificate issued successfully', 789, [
            'domains' => ['example.com', 'www.example.com'],
            'serial_number' => '03:E7:07:A9:C8:F4:5A:12',
            'expires_at' => '2024-12-31T23:59:59Z'
        ]);

        $log->setLevel(LogLevel::INFO)
            ->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/cert/abc123')
            ->setHttpMethod('GET')
            ->setHttpStatusCode(200)
            ->setDurationMs(1200)
            ->setSuccess(true);

        $this->assertSame('certificate_issue', $log->getOperation());
        $this->assertTrue($log->isInfo());
        $this->assertTrue($log->isSuccess());
        $this->assertSame(200, $log->getHttpStatusCode());
        $this->assertArrayHasKey('serial_number', $log->getContext());
    }

    public function test_businessScenario_operationFailure(): void
    {
        $log = AcmeOperationLog::orderOperation('submit', 'Order submission failed due to rate limit', 999, [
            'error_type' => 'rateLimited',
            'retry_after' => 3600,
            'domains' => ['example.com']
        ]);

        $log->setLevel(LogLevel::ERROR)
            ->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/new-order')
            ->setHttpMethod('POST')
            ->setHttpStatusCode(429)
            ->setDurationMs(500)
            ->setSuccess(false);

        $this->assertSame('order_submit', $log->getOperation());
        $this->assertTrue($log->isError());
        $this->assertFalse($log->isSuccess());
        $this->assertSame(429, $log->getHttpStatusCode());
        $this->assertStringContainsString('rate limit', $log->getMessage());
    }

    public function test_edgeCases_zeroEntityId(): void
    {
        $this->operationLog
            ->setEntityType('Test')
            ->setEntityId(0);
        
        $this->assertTrue($this->operationLog->hasRelatedEntity());
    }

    public function test_edgeCases_zeroDuration(): void
    {
        $this->operationLog
            ->setOperation('fast_operation')
            ->setMessage('Very fast operation')
            ->setDurationMs(0);
        
        $description = $this->operationLog->getFormattedDescription();
        $this->assertStringContainsString('(0ms)', $description);
    }

    public function test_edgeCases_longOperation(): void
    {
        $longOperation = str_repeat('very_long_operation_name_', 10);
        $this->operationLog->setOperation($longOperation);
        
        $this->assertSame($longOperation, $this->operationLog->getOperation());
    }

    public function test_edgeCases_complexContext(): void
    {
        $complexContext = [
            'nested' => [
                'array' => ['value1', 'value2'],
                'object' => ['key' => 'value']
            ],
            'numbers' => [1, 2, 3],
            'boolean' => true,
            'null' => null,
            'float' => 3.14
        ];
        
        $this->operationLog->setContext($complexContext);
        $this->assertSame($complexContext, $this->operationLog->getContext());
    }

    public function test_logLevelTransitions(): void
    {
        // 从 INFO 开始
        $this->assertTrue($this->operationLog->isInfo());
        $this->assertFalse($this->operationLog->isDebug());
        $this->assertFalse($this->operationLog->isWarning());
        $this->assertFalse($this->operationLog->isError());

        // 切换到 DEBUG
        $this->operationLog->setLevel(LogLevel::DEBUG);
        $this->assertFalse($this->operationLog->isInfo());
        $this->assertTrue($this->operationLog->isDebug());
        $this->assertFalse($this->operationLog->isWarning());
        $this->assertFalse($this->operationLog->isError());

        // 切换到 WARNING
        $this->operationLog->setLevel(LogLevel::WARNING);
        $this->assertFalse($this->operationLog->isInfo());
        $this->assertFalse($this->operationLog->isDebug());
        $this->assertTrue($this->operationLog->isWarning());
        $this->assertFalse($this->operationLog->isError());

        // 切换到 ERROR
        $this->operationLog->setLevel(LogLevel::ERROR);
        $this->assertFalse($this->operationLog->isInfo());
        $this->assertFalse($this->operationLog->isDebug());
        $this->assertFalse($this->operationLog->isWarning());
        $this->assertTrue($this->operationLog->isError());
    }

    public function test_httpStatusCodeMapping(): void
    {
        $statusCodes = [200, 201, 400, 401, 403, 404, 429, 500, 502, 503];
        
        foreach ($statusCodes as $code) {
            $this->operationLog->setHttpStatusCode($code);
            $this->assertSame($code, $this->operationLog->getHttpStatusCode());
            $this->assertTrue($this->operationLog->hasHttpResponse());
        }
    }

    public function test_httpMethodVariations(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        
        foreach ($methods as $method) {
            $this->operationLog->setHttpMethod($method);
            $this->assertSame($method, $this->operationLog->getHttpMethod());
        }
    }

    public function test_staticFactoryMethods_consistency(): void
    {
        $operations = ['create', 'update', 'delete', 'validate'];
        $message = 'Test operation';
        $entityId = 123;
        $details = ['test' => 'data'];

        foreach ($operations as $operation) {
            $accountLog = AcmeOperationLog::accountOperation($operation, $message, $entityId, $details);
            $orderLog = AcmeOperationLog::orderOperation($operation, $message, $entityId, $details);
            $challengeLog = AcmeOperationLog::challengeOperation($operation, $message, $entityId, $details);
            $certificateLog = AcmeOperationLog::certificateOperation($operation, $message, $entityId, $details);

            // 检查操作名称格式
            $this->assertSame("account_{$operation}", $accountLog->getOperation());
            $this->assertSame("order_{$operation}", $orderLog->getOperation());
            $this->assertSame("challenge_{$operation}", $challengeLog->getOperation());
            $this->assertSame("certificate_{$operation}", $certificateLog->getOperation());

            // 检查实体类型
            $this->assertSame('Account', $accountLog->getEntityType());
            $this->assertSame('Order', $orderLog->getEntityType());
            $this->assertSame('Challenge', $challengeLog->getEntityType());
            $this->assertSame('Certificate', $certificateLog->getEntityType());

            // 检查共同属性
            foreach ([$accountLog, $orderLog, $challengeLog, $certificateLog] as $log) {
                $this->assertSame($message, $log->getMessage());
                $this->assertSame($entityId, $log->getEntityId());
                $this->assertSame($details, $log->getContext());
                $this->assertSame(LogLevel::INFO, $log->getLevel());
                $this->assertTrue($log->isSuccess());
            }
        }
    }
} 