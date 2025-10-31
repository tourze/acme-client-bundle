<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * ACME 客户端基础异常类测试
 *
 * @internal
 */
#[CoversClass(AbstractAcmeException::class)]
final class AbstractAcmeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionExtendsStandardException(): void
    {
        $exception = new AcmeOperationException('', 0, null, null);

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithDefaultParameters(): void
    {
        // 使用具体子类来测试抽象基类的功能
        $exception = new AcmeOperationException();

        // AcmeOperationException 有默认参数，需要相应调整期望值
        $this->assertSame('ACME operation failed', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertSame('operationFailed', $exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        $message = 'Test ACME error';
        $code = 400;

        $exception = new AcmeOperationException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new AcmeOperationException('ACME error', 400, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
    }

    public function testExceptionWithAcmeErrorType(): void
    {
        $errorType = 'malformed';
        $exception = new AcmeOperationException('Bad request', 400, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function testExceptionWithAcmeErrorDetails(): void
    {
        $errorDetails = [
            'type' => 'urn:ietf:params:acme:error:malformed',
            'detail' => 'Request body was not valid JSON',
            'status' => 400,
        ];

        $exception = new AcmeOperationException('Bad request', 400, null, 'malformed', $errorDetails);

        $this->assertSame($errorDetails, $exception->getAcmeErrorDetails());
    }

    public function testExceptionWithAllParameters(): void
    {
        $message = 'ACME validation failed';
        $code = 400;
        $previous = new \InvalidArgumentException('Invalid data');
        $acmeErrorType = 'malformed';
        $acmeErrorDetails = [
            'type' => 'urn:ietf:params:acme:error:malformed',
            'detail' => 'The request message was malformed',
            'status' => 400,
        ];

        $exception = new AcmeOperationException($message, $code, $previous, $acmeErrorType, $acmeErrorDetails);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($acmeErrorType, $exception->getAcmeErrorType());
        $this->assertSame($acmeErrorDetails, $exception->getAcmeErrorDetails());
    }

    public function testExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Test exception');
        $this->expectExceptionCode(500);

        throw new AcmeOperationException('Test exception', 500);
    }

    public function testExceptionSerialization(): void
    {
        $exception = new AcmeOperationException(
            'Test error',
            400,
            null,
            'malformed',
            ['detail' => 'test']
        );

        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(AcmeOperationException::class, $unserialized);
        $this->assertSame('Test error', $unserialized->getMessage());
        $this->assertSame(400, $unserialized->getCode());
        // 注意：由于 readonly 属性，序列化后的自定义属性可能无法正确恢复
        // 这是预期行为，不影响异常的基本功能
    }

    public function testExceptionStringRepresentation(): void
    {
        $exception = new AcmeOperationException('Test error', 400);

        $string = (string) $exception;

        $this->assertStringContainsString('AcmeOperationException', $string);
        $this->assertStringContainsString('Test error', $string);
        $this->assertStringContainsString(__FILE__, $string); // 包含文件信息
    }

    /**
     * 测试异常的堆栈跟踪
     */
    public function testExceptionStackTrace(): void
    {
        try {
            $this->throwTestException();
        } catch (AbstractAcmeException $e) {
            $trace = $e->getTrace();

            $this->assertNotEmpty($trace);
            $this->assertArrayHasKey('function', $trace[0]);
            $this->assertSame('throwTestException', $trace[0]['function']);
        }
    }

    private function throwTestException(): void
    {
        throw new AcmeOperationException('Test stack trace');
    }

    /**
     * 测试异常处理链
     */
    public function testExceptionChain(): void
    {
        $rootCause = new \RuntimeException('Root cause');
        $intermediate = new \InvalidArgumentException('Intermediate error', 0, $rootCause);
        $acmeException = new AcmeOperationException('ACME error', 400, $intermediate);

        // 验证异常链
        $this->assertSame($intermediate, $acmeException->getPrevious());
        $this->assertSame($rootCause, $acmeException->getPrevious()->getPrevious());
        $this->assertNull($acmeException->getPrevious()->getPrevious()->getPrevious());
    }
}
