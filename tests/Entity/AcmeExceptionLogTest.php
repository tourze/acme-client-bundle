<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;

/**
 * AcmeExceptionLog 实体测试类
 */
class AcmeExceptionLogTest extends TestCase
{
    private AcmeExceptionLog $exceptionLog;

    protected function setUp(): void
    {
        $this->exceptionLog = new AcmeExceptionLog();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->exceptionLog->getId());
        $this->assertSame(0, $this->exceptionLog->getCode());
        $this->assertNull($this->exceptionLog->getStackTrace());
        $this->assertNull($this->exceptionLog->getFile());
        $this->assertNull($this->exceptionLog->getLine());
        $this->assertNull($this->exceptionLog->getEntityType());
        $this->assertNull($this->exceptionLog->getEntityId());
        $this->assertNull($this->exceptionLog->getContext());
        $this->assertNull($this->exceptionLog->getHttpUrl());
        $this->assertNull($this->exceptionLog->getHttpMethod());
        $this->assertNull($this->exceptionLog->getHttpStatusCode());
        $this->assertFalse($this->exceptionLog->isResolved());
    }

    public function test_exceptionClass_getterSetter(): void
    {
        $exceptionClass = 'RuntimeException';
        $result = $this->exceptionLog->setExceptionClass($exceptionClass);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($exceptionClass, $this->exceptionLog->getExceptionClass());
    }

    public function test_message_getterSetter(): void
    {
        $message = 'Test exception message';
        $result = $this->exceptionLog->setMessage($message);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($message, $this->exceptionLog->getMessage());
    }

    public function test_code_getterSetter(): void
    {
        $this->assertSame(0, $this->exceptionLog->getCode());

        $code = 500;
        $result = $this->exceptionLog->setCode($code);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($code, $this->exceptionLog->getCode());
    }

    public function test_stackTrace_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getStackTrace());

        $stackTrace = "#0 /path/to/file.php(123): function()\n#1 {main}";
        $result = $this->exceptionLog->setStackTrace($stackTrace);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($stackTrace, $this->exceptionLog->getStackTrace());
    }

    public function test_stackTrace_setToNull(): void
    {
        $this->exceptionLog->setStackTrace('test trace');
        $this->exceptionLog->setStackTrace(null);
        
        $this->assertNull($this->exceptionLog->getStackTrace());
    }

    public function test_file_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getFile());

        $file = '/path/to/exception/file.php';
        $result = $this->exceptionLog->setFile($file);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($file, $this->exceptionLog->getFile());
    }

    public function test_file_setToNull(): void
    {
        $this->exceptionLog->setFile('/test/file.php');
        $this->exceptionLog->setFile(null);
        
        $this->assertNull($this->exceptionLog->getFile());
    }

    public function test_line_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getLine());

        $line = 123;
        $result = $this->exceptionLog->setLine($line);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($line, $this->exceptionLog->getLine());
    }

    public function test_line_setToNull(): void
    {
        $this->exceptionLog->setLine(456);
        $this->exceptionLog->setLine(null);
        
        $this->assertNull($this->exceptionLog->getLine());
    }

    public function test_entityType_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getEntityType());

        $entityType = 'Order';
        $result = $this->exceptionLog->setEntityType($entityType);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($entityType, $this->exceptionLog->getEntityType());
    }

    public function test_entityType_setToNull(): void
    {
        $this->exceptionLog->setEntityType('Account');
        $this->exceptionLog->setEntityType(null);
        
        $this->assertNull($this->exceptionLog->getEntityType());
    }

    public function test_entityId_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getEntityId());

        $entityId = 123;
        $result = $this->exceptionLog->setEntityId($entityId);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($entityId, $this->exceptionLog->getEntityId());
    }

    public function test_entityId_setToNull(): void
    {
        $this->exceptionLog->setEntityId(456);
        $this->exceptionLog->setEntityId(null);
        
        $this->assertNull($this->exceptionLog->getEntityId());
    }

    public function test_context_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getContext());

        $context = [
            'operation' => 'certificate_request',
            'domain' => 'example.com',
            'attempt' => 3
        ];
        $result = $this->exceptionLog->setContext($context);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($context, $this->exceptionLog->getContext());
    }

    public function test_context_setToNull(): void
    {
        $this->exceptionLog->setContext(['test' => 'value']);
        $this->exceptionLog->setContext(null);
        
        $this->assertNull($this->exceptionLog->getContext());
    }

    public function test_httpUrl_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getHttpUrl());

        $httpUrl = 'https://acme-v02.api.letsencrypt.org/acme/new-order';
        $result = $this->exceptionLog->setHttpUrl($httpUrl);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($httpUrl, $this->exceptionLog->getHttpUrl());
    }

    public function test_httpUrl_setToNull(): void
    {
        $this->exceptionLog->setHttpUrl('https://example.com');
        $this->exceptionLog->setHttpUrl(null);
        
        $this->assertNull($this->exceptionLog->getHttpUrl());
    }

    public function test_httpMethod_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getHttpMethod());

        $httpMethod = 'POST';
        $result = $this->exceptionLog->setHttpMethod($httpMethod);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($httpMethod, $this->exceptionLog->getHttpMethod());
    }

    public function test_httpMethod_setToNull(): void
    {
        $this->exceptionLog->setHttpMethod('GET');
        $this->exceptionLog->setHttpMethod(null);
        
        $this->assertNull($this->exceptionLog->getHttpMethod());
    }

    public function test_httpStatusCode_getterSetter(): void
    {
        $this->assertNull($this->exceptionLog->getHttpStatusCode());

        $statusCode = 500;
        $result = $this->exceptionLog->setHttpStatusCode($statusCode);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($statusCode, $this->exceptionLog->getHttpStatusCode());
    }

    public function test_httpStatusCode_setToNull(): void
    {
        $this->exceptionLog->setHttpStatusCode(404);
        $this->exceptionLog->setHttpStatusCode(null);
        
        $this->assertNull($this->exceptionLog->getHttpStatusCode());
    }

    public function test_resolved_getterSetter(): void
    {
        $this->assertFalse($this->exceptionLog->isResolved());

        $result = $this->exceptionLog->setResolved(true);
        $this->assertSame($this->exceptionLog, $result);
        $this->assertTrue($this->exceptionLog->isResolved());

        $this->exceptionLog->setResolved(false);
        $this->assertFalse($this->exceptionLog->isResolved());
    }

    public function test_fromException_basicException(): void
    {
        $exception = new \RuntimeException('Test exception message', 123);
        
        $log = AcmeExceptionLog::fromException($exception);
        
        $this->assertInstanceOf(AcmeExceptionLog::class, $log);
        $this->assertSame('RuntimeException', $log->getExceptionClass());
        $this->assertSame('Test exception message', $log->getMessage());
        $this->assertSame(123, $log->getCode());
        $this->assertNotNull($log->getStackTrace());
        $this->assertNotNull($log->getFile());
        $this->assertNotNull($log->getLine());
        $this->assertNull($log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
    }

    public function test_fromException_withEntityInfo(): void
    {
        $exception = new \InvalidArgumentException('Invalid domain', 400);
        $entityType = 'Order';
        $entityId = 456;
        $context = ['domain' => 'invalid.domain'];
        
        $log = AcmeExceptionLog::fromException($exception, $entityType, $entityId, $context);
        
        $this->assertSame('InvalidArgumentException', $log->getExceptionClass());
        $this->assertSame('Invalid domain', $log->getMessage());
        $this->assertSame(400, $log->getCode());
        $this->assertSame($entityType, $log->getEntityType());
        $this->assertSame($entityId, $log->getEntityId());
        $this->assertSame($context, $log->getContext());
    }

    public function test_fromException_withNullValues(): void
    {
        $exception = new \Exception('Test');
        
        $log = AcmeExceptionLog::fromException($exception, null, null, null);
        
        $this->assertSame('Exception', $log->getExceptionClass());
        $this->assertNull($log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
    }

    public function test_getShortDescription_withFileAndLine(): void
    {
        $this->exceptionLog
            ->setExceptionClass('RuntimeException')
            ->setMessage('Test exception')
            ->setFile('/path/to/very/long/file/name.php')
            ->setLine(123);
        
        $description = $this->exceptionLog->getShortDescription();
        
        $this->assertSame('RuntimeException: Test exception in name.php:123', $description);
    }

    public function test_getShortDescription_withoutFileAndLine(): void
    {
        $this->exceptionLog
            ->setExceptionClass('InvalidArgumentException')
            ->setMessage('Invalid parameter');
        
        $description = $this->exceptionLog->getShortDescription();
        
        $this->assertSame('InvalidArgumentException: Invalid parameter', $description);
    }

    public function test_getShortDescription_withFileOnly(): void
    {
        $this->exceptionLog
            ->setExceptionClass('LogicException')
            ->setMessage('Logic error')
            ->setFile('/path/to/file.php');
        
        $description = $this->exceptionLog->getShortDescription();
        
        $this->assertSame('LogicException: Logic error', $description);
    }

    public function test_getShortDescription_withLineOnly(): void
    {
        $this->exceptionLog
            ->setExceptionClass('BadMethodCallException')
            ->setMessage('Bad method call')
            ->setLine(456);
        
        $description = $this->exceptionLog->getShortDescription();
        
        $this->assertSame('BadMethodCallException: Bad method call', $description);
    }

    public function test_hasRelatedEntity_withBothValues(): void
    {
        $this->exceptionLog
            ->setEntityType('Certificate')
            ->setEntityId(789);
        
        $this->assertTrue($this->exceptionLog->hasRelatedEntity());
    }

    public function test_hasRelatedEntity_withEntityTypeOnly(): void
    {
        $this->exceptionLog->setEntityType('Account');
        
        $this->assertFalse($this->exceptionLog->hasRelatedEntity());
    }

    public function test_hasRelatedEntity_withEntityIdOnly(): void
    {
        $this->exceptionLog->setEntityId(123);
        
        $this->assertFalse($this->exceptionLog->hasRelatedEntity());
    }

    public function test_hasRelatedEntity_withNeitherValue(): void
    {
        $this->assertFalse($this->exceptionLog->hasRelatedEntity());
    }

    public function test_toString(): void
    {
        $this->exceptionLog->setExceptionClass('RuntimeException');
        
        $expected = 'Exception #0: RuntimeException';
        $this->assertSame($expected, (string) $this->exceptionLog);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->exceptionLog);
    }

    public function test_fluentInterface_chaining(): void
    {
        $exceptionClass = 'TestException';
        $message = 'Test message';
        $code = 500;
        $stackTrace = 'Test stack trace';
        $file = '/test/file.php';
        $line = 123;
        $entityType = 'Order';
        $entityId = 456;
        $context = ['test' => 'context'];
        $httpUrl = 'https://example.com';
        $httpMethod = 'POST';
        $httpStatusCode = 400;

        $result = $this->exceptionLog
            ->setExceptionClass($exceptionClass)
            ->setMessage($message)
            ->setCode($code)
            ->setStackTrace($stackTrace)
            ->setFile($file)
            ->setLine($line)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setContext($context)
            ->setHttpUrl($httpUrl)
            ->setHttpMethod($httpMethod)
            ->setHttpStatusCode($httpStatusCode)
            ->setResolved(true);

        $this->assertSame($this->exceptionLog, $result);
        $this->assertSame($exceptionClass, $this->exceptionLog->getExceptionClass());
        $this->assertSame($message, $this->exceptionLog->getMessage());
        $this->assertSame($code, $this->exceptionLog->getCode());
        $this->assertSame($stackTrace, $this->exceptionLog->getStackTrace());
        $this->assertSame($file, $this->exceptionLog->getFile());
        $this->assertSame($line, $this->exceptionLog->getLine());
        $this->assertSame($entityType, $this->exceptionLog->getEntityType());
        $this->assertSame($entityId, $this->exceptionLog->getEntityId());
        $this->assertSame($context, $this->exceptionLog->getContext());
        $this->assertSame($httpUrl, $this->exceptionLog->getHttpUrl());
        $this->assertSame($httpMethod, $this->exceptionLog->getHttpMethod());
        $this->assertSame($httpStatusCode, $this->exceptionLog->getHttpStatusCode());
        $this->assertTrue($this->exceptionLog->isResolved());
    }

    public function test_businessScenario_acmeApiError(): void
    {
        $this->exceptionLog
            ->setExceptionClass('Tourze\\ACMEClientBundle\\Exception\\AcmeServerException')
            ->setMessage('Rate limit exceeded')
            ->setCode(429)
            ->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/new-order')
            ->setHttpMethod('POST')
            ->setHttpStatusCode(429)
            ->setEntityType('Order')
            ->setEntityId(123)
            ->setContext([
                'domain' => 'example.com',
                'retry_after' => 3600
            ]);

        $this->assertStringContainsString('Rate limit exceeded', $this->exceptionLog->getMessage());
        $this->assertSame(429, $this->exceptionLog->getCode());
        $this->assertSame(429, $this->exceptionLog->getHttpStatusCode());
        $this->assertTrue($this->exceptionLog->hasRelatedEntity());
        $this->assertFalse($this->exceptionLog->isResolved());
    }

    public function test_businessScenario_certificateValidationError(): void
    {
        $this->exceptionLog
            ->setExceptionClass('Tourze\\ACMEClientBundle\\Exception\\AcmeValidationException')
            ->setMessage('DNS challenge validation failed')
            ->setEntityType('Challenge')
            ->setEntityId(789)
            ->setContext([
                'challenge_type' => 'dns-01',
                'domain' => 'example.com',
                'dns_record' => '_acme-challenge.example.com'
            ]);

        $this->assertStringContainsString('DNS challenge', $this->exceptionLog->getMessage());
        $this->assertSame('Challenge', $this->exceptionLog->getEntityType());
        $this->assertTrue($this->exceptionLog->hasRelatedEntity());
        $this->assertArrayHasKey('challenge_type', $this->exceptionLog->getContext());
    }

    public function test_businessScenario_networkError(): void
    {
        $this->exceptionLog
            ->setExceptionClass('GuzzleHttp\\Exception\\ConnectException')
            ->setMessage('Connection timeout')
            ->setHttpUrl('https://acme-v02.api.letsencrypt.org/directory')
            ->setHttpMethod('GET')
            ->setContext(['timeout' => 30]);

        $this->assertStringContainsString('timeout', $this->exceptionLog->getMessage());
        $this->assertStringContainsString('letsencrypt', $this->exceptionLog->getHttpUrl());
        $this->assertSame('GET', $this->exceptionLog->getHttpMethod());
        $this->assertFalse($this->exceptionLog->hasRelatedEntity());
    }

    public function test_businessScenario_exceptionResolution(): void
    {
        // 异常发生
        $this->exceptionLog
            ->setExceptionClass('RuntimeException')
            ->setMessage('Temporary error')
            ->setResolved(false);

        $this->assertFalse($this->exceptionLog->isResolved());

        // 异常解决
        $this->exceptionLog->setResolved(true);
        $this->assertTrue($this->exceptionLog->isResolved());
    }

    public function test_edgeCases_emptyMessage(): void
    {
        $this->exceptionLog
            ->setExceptionClass('Exception')
            ->setMessage('');
        
        $description = $this->exceptionLog->getShortDescription();
        $this->assertSame('Exception: ', $description);
    }

    public function test_edgeCases_longStackTrace(): void
    {
        $longStackTrace = str_repeat("#0 /very/long/path/to/file.php(123): function()\n", 100);
        $this->exceptionLog->setStackTrace($longStackTrace);
        
        $this->assertSame($longStackTrace, $this->exceptionLog->getStackTrace());
    }

    public function test_edgeCases_negativeCode(): void
    {
        $this->exceptionLog->setCode(-1);
        $this->assertSame(-1, $this->exceptionLog->getCode());
    }

    public function test_edgeCases_zeroEntityId(): void
    {
        $this->exceptionLog
            ->setEntityType('Test')
            ->setEntityId(0);
        
        $this->assertTrue($this->exceptionLog->hasRelatedEntity());
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
            'null' => null
        ];
        
        $this->exceptionLog->setContext($complexContext);
        $this->assertSame($complexContext, $this->exceptionLog->getContext());
    }

    public function test_staticFactory_preservesExceptionDetails(): void
    {
        // 创建一个有具体文件和行号的异常
        try {
            throw new \Tourze\ACMEClientBundle\Exception\AcmeClientException('Test exception for factory', 999);
        } catch (\Throwable $exception) {
            $log = AcmeExceptionLog::fromException($exception);
            
            $this->assertSame('Tourze\\ACMEClientBundle\\Exception\\AcmeClientException', $log->getExceptionClass());
            $this->assertSame('Test exception for factory', $log->getMessage());
            $this->assertSame(999, $log->getCode());
            $this->assertStringContainsString(__FILE__, $log->getFile());
            $this->assertIsInt($log->getLine());
            // 堆栈跟踪应该包含异常信息，但不一定包含特定文件名（因为 PHPUnit 调用栈）
            $this->assertNotNull($log->getStackTrace());
            $this->assertNotEmpty($log->getStackTrace());
        }
    }

    public function test_httpStatusCodeMapping(): void
    {
        $statusCodes = [200, 400, 401, 403, 404, 429, 500, 502, 503];
        
        foreach ($statusCodes as $code) {
            $this->exceptionLog->setHttpStatusCode($code);
            $this->assertSame($code, $this->exceptionLog->getHttpStatusCode());
        }
    }

    public function test_httpMethodVariations(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        
        foreach ($methods as $method) {
            $this->exceptionLog->setHttpMethod($method);
            $this->assertSame($method, $this->exceptionLog->getHttpMethod());
        }
    }
} 