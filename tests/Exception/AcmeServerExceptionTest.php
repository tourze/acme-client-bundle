<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * ACME 服务器异常测试
 *
 * @internal
 */
#[CoversClass(AcmeServerException::class)]
final class AcmeServerExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionExtendsAbstractAcmeException(): void
    {
        $exception = new AcmeServerException();

        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithDefaultParameters(): void
    {
        $exception = new AcmeServerException();

        $this->assertSame('ACME server error', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertSame('serverInternal', $exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
    }

    public function testExceptionWithCustomMessageAndCode(): void
    {
        $message = 'Internal server error';
        $code = 502;

        $exception = new AcmeServerException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('Database connection failed');
        $exception = new AcmeServerException('Server error', 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithAcmeErrorType(): void
    {
        $errorType = 'serverInternal';
        $exception = new AcmeServerException('Server error', 500, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function testExceptionWithCustomAcmeErrorType(): void
    {
        $errorType = 'badGateway';
        $exception = new AcmeServerException('Bad gateway', 502, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function testExceptionWithAcmeErrorDetails(): void
    {
        $errorDetails = [
            'type' => 'urn:ietf:params:acme:error:serverInternal',
            'detail' => 'The server experienced an internal error',
            'status' => 500,
        ];

        $exception = new AcmeServerException('Server error', 500, null, 'serverInternal', $errorDetails);

        $this->assertSame($errorDetails, $exception->getAcmeErrorDetails());
    }

    public function testExceptionWithAllParameters(): void
    {
        $message = 'Service temporarily unavailable';
        $code = 503;
        $previous = new \RuntimeException('Service down');
        $acmeErrorType = 'serviceUnavailable';
        $acmeErrorDetails = [
            'type' => 'urn:ietf:params:acme:error:serviceUnavailable',
            'detail' => 'The server is temporarily unable to service your request',
            'status' => 503,
            'retry-after' => 300,
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

    public function testExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(AcmeServerException::class);
        $this->expectExceptionMessage('Test server error');
        $this->expectExceptionCode(500);

        throw new AcmeServerException('Test server error', 500);
    }

    public function testExceptionCanBeCaughtAsAbstractAcmeException(): void
    {
        try {
            throw new AcmeServerException('Server error test');
        } catch (AbstractAcmeException $e) {
            $this->assertInstanceOf(AcmeServerException::class, $e);
            $this->assertSame('Server error test', $e->getMessage());
        }
    }

    /**
     * 测试不同的服务器错误状态码
     */
    public function testDifferentServerErrorCodes(): void
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
    public function testServerMaintenanceScenario(): void
    {
        $exception = new AcmeServerException(
            'Server under maintenance',
            503,
            null,
            'serviceUnavailable',
            [
                'detail' => 'Server is temporarily down for maintenance',
                'retry-after' => 3600,
                'maintenance' => true,
            ]
        );

        // 验证错误详情中包含维护信息
        $details = $exception->getAcmeErrorDetails();
        $this->assertIsArray($details);
        $this->assertArrayHasKey('maintenance', $details);
        $this->assertTrue($details['maintenance']);
        $this->assertArrayHasKey('retry-after', $details);
        $this->assertSame(3600, $details['retry-after']);
    }

    public function testInheritanceHierarchy(): void
    {
        $exception = new AcmeServerException();

        $this->assertInstanceOf(AcmeServerException::class, $exception);
        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }
}
