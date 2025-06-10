<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;

/**
 * ACME 服务器异常测试
 */
class AcmeServerExceptionTest extends TestCase
{
    public function test_exception_extends_acme_client_exception(): void
    {
        $exception = new AcmeServerException();

        $this->assertInstanceOf(AcmeClientException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_exception_with_default_parameters(): void
    {
        $exception = new AcmeServerException();

        $this->assertSame('ACME server error', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertSame('serverInternal', $exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
    }

    public function test_exception_with_custom_message_and_code(): void
    {
        $message = 'Internal server error';
        $code = 502;

        $exception = new AcmeServerException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function test_exception_with_previous_exception(): void
    {
        $previous = new \RuntimeException('Database connection failed');
        $exception = new AcmeServerException('Server error', 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_exception_with_acme_error_type(): void
    {
        $errorType = 'serverInternal';
        $exception = new AcmeServerException('Server error', 500, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function test_exception_with_custom_acme_error_type(): void
    {
        $errorType = 'badGateway';
        $exception = new AcmeServerException('Bad gateway', 502, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function test_exception_with_acme_error_details(): void
    {
        $errorDetails = [
            'type' => 'urn:ietf:params:acme:error:serverInternal',
            'detail' => 'The server experienced an internal error',
            'status' => 500
        ];

        $exception = new AcmeServerException('Server error', 500, null, 'serverInternal', $errorDetails);

        $this->assertSame($errorDetails, $exception->getAcmeErrorDetails());
    }

    public function test_exception_with_all_parameters(): void
    {
        $message = 'Service temporarily unavailable';
        $code = 503;
        $previous = new \RuntimeException('Service down');
        $acmeErrorType = 'serviceUnavailable';
        $acmeErrorDetails = [
            'type' => 'urn:ietf:params:acme:error:serviceUnavailable',
            'detail' => 'The server is temporarily unable to service your request',
            'status' => 503,
            'retry-after' => 300
        ];

        $exception = new AcmeServerException(
            $message,
            $code,
            $previous,
            $acmeErrorType,
            $acmeErrorDetails
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($acmeErrorType, $exception->getAcmeErrorType());
        $this->assertSame($acmeErrorDetails, $exception->getAcmeErrorDetails());
    }

    public function test_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(AcmeServerException::class);
        $this->expectExceptionMessage('Test server error');
        $this->expectExceptionCode(500);

        throw new AcmeServerException('Test server error', 500);
    }

    public function test_exception_can_be_caught_as_acme_client_exception(): void
    {
        try {
            throw new AcmeServerException('Server error test');
        } catch (AcmeClientException $e) {
            $this->assertInstanceOf(AcmeServerException::class, $e);
            $this->assertSame('Server error test', $e->getMessage());
        }
    }

    /**
     * 测试不同的服务器错误状态码
     */
    public function test_different_server_error_codes(): void
    {
        $testCases = [
            [500, 'Internal Server Error'],
            [502, 'Bad Gateway'],
            [503, 'Service Unavailable'],
        ];

        foreach ($testCases as [$code, $message]) {
            $exception = new AcmeServerException($message, $code);

            $this->assertSame($code, $exception->getCode());
            $this->assertSame($message, $exception->getMessage());
            $this->assertInstanceOf(AcmeServerException::class, $exception);
        }
    }

    /**
     * 测试服务器异常的业务逻辑场景
     */
    public function test_server_maintenance_scenario(): void
    {
        $exception = new AcmeServerException(
            'Server under maintenance',
            503,
            null,
            'serviceUnavailable',
            [
                'detail' => 'Server is temporarily down for maintenance',
                'retry-after' => 3600,
                'maintenance' => true
            ]
        );

        // 验证错误详情中包含维护信息
        $details = $exception->getAcmeErrorDetails();
        $this->assertArrayHasKey('maintenance', $details);
        $this->assertTrue($details['maintenance']);
        $this->assertArrayHasKey('retry-after', $details);
        $this->assertSame(3600, $details['retry-after']);
    }

    public function test_inheritance_hierarchy(): void
    {
        $exception = new AcmeServerException();

        $this->assertInstanceOf(AcmeServerException::class, $exception);
        $this->assertInstanceOf(AcmeClientException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }
}
