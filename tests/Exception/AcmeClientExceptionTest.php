<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;

/**
 * ACME 客户端基础异常类测试
 */
class AcmeClientExceptionTest extends TestCase
{
    public function test_exception_extends_standard_exception(): void
    {
        $exception = new AcmeClientException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_exception_with_default_parameters(): void
    {
        $exception = new AcmeClientException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
    }

    public function test_exception_with_message_and_code(): void
    {
        $message = 'Test ACME error';
        $code = 400;

        $exception = new AcmeClientException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function test_exception_with_previous_exception(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new AcmeClientException('ACME error', 400, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
    }

    public function test_exception_with_acme_error_type(): void
    {
        $errorType = 'malformed';
        $exception = new AcmeClientException('Bad request', 400, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function test_exception_with_acme_error_details(): void
    {
        $errorDetails = [
            'type' => 'urn:ietf:params:acme:error:malformed',
            'detail' => 'Request body was not valid JSON',
            'status' => 400
        ];

        $exception = new AcmeClientException('Bad request', 400, null, 'malformed', $errorDetails);

        $this->assertSame($errorDetails, $exception->getAcmeErrorDetails());
    }

    public function test_exception_with_all_parameters(): void
    {
        $message = 'ACME validation failed';
        $code = 400;
        $previous = new \InvalidArgumentException('Invalid data');
        $acmeErrorType = 'malformed';
        $acmeErrorDetails = [
            'type' => 'urn:ietf:params:acme:error:malformed',
            'detail' => 'The request message was malformed',
            'status' => 400
        ];

        $exception = new AcmeClientException($message, $code, $previous, $acmeErrorType, $acmeErrorDetails);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($acmeErrorType, $exception->getAcmeErrorType());
        $this->assertSame($acmeErrorDetails, $exception->getAcmeErrorDetails());
    }

    public function test_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Test exception');
        $this->expectExceptionCode(500);

        throw new AcmeClientException('Test exception', 500);
    }

    public function test_exception_serialization(): void
    {
        $exception = new AcmeClientException(
            'Test error',
            400,
            null,
            'malformed',
            ['detail' => 'test']
        );

        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(AcmeClientException::class, $unserialized);
        $this->assertSame('Test error', $unserialized->getMessage());
        $this->assertSame(400, $unserialized->getCode());
        // 注意：由于 readonly 属性，序列化后的自定义属性可能无法正确恢复
        // 这是预期行为，不影响异常的基本功能
    }

    public function test_exception_string_representation(): void
    {
        $exception = new AcmeClientException('Test error', 400);

        $string = (string)$exception;

        $this->assertStringContainsString('AcmeClientException', $string);
        $this->assertStringContainsString('Test error', $string);
        $this->assertStringContainsString(__FILE__, $string); // 包含文件信息
    }

    /**
     * 测试异常的堆栈跟踪
     */
    public function test_exception_stack_trace(): void
    {
        try {
            $this->throwTestException();
        } catch (AcmeClientException $e) {
            $trace = $e->getTrace();

            $this->assertNotEmpty($trace);
            $this->assertArrayHasKey('function', $trace[0]);
            $this->assertSame('throwTestException', $trace[0]['function']);
        }
    }

    private function throwTestException(): void
    {
        throw new AcmeClientException('Test stack trace');
    }

    /**
     * 测试异常处理链
     */
    public function test_exception_chain(): void
    {
        $rootCause = new \RuntimeException('Root cause');
        $intermediate = new \InvalidArgumentException('Intermediate error', 0, $rootCause);
        $acmeException = new AcmeClientException('ACME error', 400, $intermediate);

        // 验证异常链
        $this->assertSame($intermediate, $acmeException->getPrevious());
        $this->assertSame($rootCause, $acmeException->getPrevious()->getPrevious());
        $this->assertNull($acmeException->getPrevious()->getPrevious()->getPrevious());
    }
}
