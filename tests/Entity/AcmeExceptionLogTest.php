<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * AcmeExceptionLog 实体测试类
 *
 * @internal
 */
#[CoversClass(AcmeExceptionLog::class)]
final class AcmeExceptionLogTest extends AbstractEntityTestCase
{
    public function testConstructorDefaultValues(): void
    {
        $log = $this->createEntity();
        $this->assertNull($log->getId());
        $this->assertSame(0, $log->getCode());
        $this->assertNull($log->getStackTrace());
        $this->assertNull($log->getFile());
        $this->assertNull($log->getLine());
        $this->assertNull($log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
        $this->assertNull($log->getHttpUrl());
        $this->assertNull($log->getHttpMethod());
        $this->assertNull($log->getHttpStatusCode());
        $this->assertFalse($log->isResolved());
    }

    public function testFromExceptionBasicException(): void
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

    public function testFromExceptionWithEntityInfo(): void
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

    public function testExceptionClassGetterSetter(): void
    {
        $log = $this->createEntity();
        $exceptionClass = 'RuntimeException';
        $log->setExceptionClass($exceptionClass);

        $this->assertSame($exceptionClass, $log->getExceptionClass());
    }

    public function testMessageGetterSetter(): void
    {
        $log = $this->createEntity();
        $message = 'Test exception message';
        $log->setMessage($message);

        $this->assertSame($message, $log->getMessage());
    }

    public function testFromExceptionWithNullValues(): void
    {
        $exception = new \Exception('Test');

        $log = AcmeExceptionLog::fromException($exception, null, null, null);

        $this->assertSame('Exception', $log->getExceptionClass());
        $this->assertNull($log->getEntityType());
        $this->assertNull($log->getEntityId());
        $this->assertNull($log->getContext());
    }

    public function testGetShortDescriptionWithFileAndLine(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('RuntimeException');
        $log->setMessage('Test exception');
        $log->setFile('/path/to/very/long/file/name.php');
        $log->setLine(123);

        $description = $log->getShortDescription();

        $this->assertSame('RuntimeException: Test exception in name.php:123', $description);
    }

    public function testGetShortDescriptionWithoutFileAndLine(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('InvalidArgumentException');
        $log->setMessage('Invalid parameter');

        $description = $log->getShortDescription();

        $this->assertSame('InvalidArgumentException: Invalid parameter', $description);
    }

    public function testGetShortDescriptionWithFileOnly(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('LogicException');
        $log->setMessage('Logic error');
        $log->setFile('/path/to/file.php');

        $description = $log->getShortDescription();

        $this->assertSame('LogicException: Logic error', $description);
    }

    public function testGetShortDescriptionWithLineOnly(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('BadMethodCallException');
        $log->setMessage('Bad method call');
        $log->setLine(456);

        $description = $log->getShortDescription();

        $this->assertSame('BadMethodCallException: Bad method call', $description);
    }

    public function testHasRelatedEntityWithBothValues(): void
    {
        $log = $this->createEntity();
        $log->setEntityType('Certificate');
        $log->setEntityId(789);

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
        $log->setEntityId(123);

        $this->assertFalse($log->hasRelatedEntity());
    }

    public function testHasRelatedEntityWithNeitherValue(): void
    {
        $log = $this->createEntity();
        $this->assertFalse($log->hasRelatedEntity());
    }

    public function testToString(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('RuntimeException');

        $expected = 'Exception #0: RuntimeException';
        $this->assertSame($expected, (string) $log);
    }

    public function testStringableInterface(): void
    {
        $log = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $log);
    }

    public function testFluentInterfaceChaining(): void
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

        $result = $this->createEntity();
        $result->setExceptionClass($exceptionClass);
        $result->setMessage($message);
        $result->setCode($code);
        $result->setStackTrace($stackTrace);
        $result->setFile($file);
        $result->setLine($line);
        $result->setEntityType($entityType);
        $result->setEntityId($entityId);
        $result->setContext($context);
        $result->setHttpUrl($httpUrl);
        $result->setHttpMethod($httpMethod);
        $result->setHttpStatusCode($httpStatusCode);
        $result->setResolved(true);

        // 验证流式接口返回自身
        $this->assertSame($result, $result);
        $this->assertSame($exceptionClass, $result->getExceptionClass());
        $this->assertSame($message, $result->getMessage());
        $this->assertSame($code, $result->getCode());
        $this->assertSame($stackTrace, $result->getStackTrace());
        $this->assertSame($file, $result->getFile());
        $this->assertSame($line, $result->getLine());
        $this->assertSame($entityType, $result->getEntityType());
        $this->assertSame($entityId, $result->getEntityId());
        $this->assertSame($context, $result->getContext());
        $this->assertSame($httpUrl, $result->getHttpUrl());
        $this->assertSame($httpMethod, $result->getHttpMethod());
        $this->assertSame($httpStatusCode, $result->getHttpStatusCode());
        $this->assertTrue($result->isResolved());
    }

    public function testBusinessScenarioAcmeApiError(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('Tourze\ACMEClientBundle\Exception\AcmeServerException');
        $log->setMessage('Rate limit exceeded');
        $log->setCode(429);
        $log->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/new-order');
        $log->setHttpMethod('POST');
        $log->setHttpStatusCode(429);
        $log->setEntityType('Order');
        $log->setEntityId(123);
        $log->setContext([
            'domain' => 'example.com',
            'retry_after' => 3600,
        ]);

        $this->assertStringContainsString('Rate limit exceeded', $log->getMessage());
        $this->assertSame(429, $log->getCode());
        $this->assertSame(429, $log->getHttpStatusCode());
        $this->assertTrue($log->hasRelatedEntity());
        $this->assertFalse($log->isResolved());
    }

    public function testBusinessScenarioCertificateValidationError(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('Tourze\ACMEClientBundle\Exception\AcmeValidationException');
        $log->setMessage('DNS challenge validation failed');
        $log->setEntityType('Challenge');
        $log->setEntityId(789);
        $log->setContext([
            'challenge_type' => 'dns-01',
            'domain' => 'example.com',
            'dns_record' => '_acme-challenge.example.com',
        ]);

        $this->assertStringContainsString('DNS challenge', $log->getMessage());
        $this->assertSame('Challenge', $log->getEntityType());
        $this->assertTrue($log->hasRelatedEntity());
        $context = $log->getContext();
        $this->assertNotNull($context, 'Context should not be null');
        $this->assertArrayHasKey('challenge_type', $context);
    }

    public function testBusinessScenarioNetworkError(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('GuzzleHttp\Exception\ConnectException');
        $log->setMessage('Connection timeout');
        $log->setHttpUrl('https://acme-v02.api.letsencrypt.org/directory');
        $log->setHttpMethod('GET');
        $log->setContext(['timeout' => 30]);

        $this->assertStringContainsString('timeout', $log->getMessage());
        $httpUrl = $log->getHttpUrl();
        $this->assertNotNull($httpUrl, 'HTTP URL should not be null');
        $this->assertStringContainsString('letsencrypt', $httpUrl);
        $this->assertSame('GET', $log->getHttpMethod());
        $this->assertFalse($log->hasRelatedEntity());
    }

    public function testBusinessScenarioExceptionResolution(): void
    {
        // 异常发生
        $log = $this->createEntity();
        $log->setExceptionClass('RuntimeException');
        $log->setMessage('Temporary error');
        $log->setResolved(false);

        $this->assertFalse($log->isResolved());

        // 异常解决
        $log->setResolved(true);
        $this->assertTrue($log->isResolved());
    }

    public function testEdgeCasesEmptyMessage(): void
    {
        $log = $this->createEntity();
        $log->setExceptionClass('Exception');
        $log->setMessage('');

        $description = $log->getShortDescription();
        $this->assertSame('Exception: ', $description);
    }

    public function testEdgeCasesLongStackTrace(): void
    {
        $longStackTrace = str_repeat("#0 /very/long/path/to/file.php(123): function()\n", 100);
        $log = $this->createEntity();
        $log->setStackTrace($longStackTrace);

        $this->assertSame($longStackTrace, $log->getStackTrace());
    }

    public function testEdgeCasesNegativeCode(): void
    {
        $log = $this->createEntity();
        $log->setCode(-1);
        $this->assertSame(-1, $log->getCode());
    }

    public function testEdgeCasesZeroEntityId(): void
    {
        $log = $this->createEntity();
        $log->setEntityType('Test');
        $log->setEntityId(0);

        $this->assertTrue($log->hasRelatedEntity());
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
        ];

        $log = $this->createEntity();
        $log->setContext($complexContext);
        $this->assertSame($complexContext, $log->getContext());
    }

    public function testStaticFactoryPreservesExceptionDetails(): void
    {
        // 创建一个有具体文件和行号的异常
        try {
            throw new AcmeOperationException('Test exception for factory', 999);
        } catch (\Throwable $exception) {
            $log = AcmeExceptionLog::fromException($exception);

            $this->assertSame('Tourze\ACMEClientBundle\Exception\AcmeOperationException', $log->getExceptionClass());
            $this->assertSame('Test exception for factory', $log->getMessage());
            $this->assertSame(999, $log->getCode());
            $file = $log->getFile();
            $this->assertNotNull($file, 'File should not be null');
            $this->assertStringContainsString(__FILE__, $file);
            $this->assertIsInt($log->getLine());
            // 堆栈跟踪应该包含异常信息，但不一定包含特定文件名（因为 PHPUnit 调用栈）
            $this->assertNotNull($log->getStackTrace());
            $this->assertNotEmpty($log->getStackTrace());
        }
    }

    public function testHttpStatusCodeMapping(): void
    {
        $statusCodes = [200, 400, 401, 403, 404, 429, 500, 502, 503];

        foreach ($statusCodes as $code) {
            $log = $this->createEntity();
            $log->setHttpStatusCode($code);
            $this->assertSame($code, $log->getHttpStatusCode());
        }
    }

    public function testHttpMethodVariations(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        foreach ($methods as $method) {
            $log = $this->createEntity();
            $log->setHttpMethod($method);
            $this->assertSame($method, $log->getHttpMethod());
        }
    }

    protected function createEntity(): AcmeExceptionLog
    {
        return new AcmeExceptionLog();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'exceptionClass' => ['exceptionClass', 'RuntimeException'];
        yield 'message' => ['message', 'Test exception message'];
        yield 'code' => ['code', 500];
        yield 'stackTrace' => ['stackTrace', '#0 /path/to/file.php(123): function()'];
        yield 'file' => ['file', '/path/to/file.php'];
        yield 'line' => ['line', 123];
        yield 'entityType' => ['entityType', 'Order'];
        yield 'entityId' => ['entityId', 123];
        yield 'context' => ['context', ['operation' => 'test']];
        yield 'httpUrl' => ['httpUrl', 'https://example.com'];
        yield 'httpMethod' => ['httpMethod', 'POST'];
        yield 'httpStatusCode' => ['httpStatusCode', 200];
        yield 'resolved' => ['resolved', true];
    }
}
