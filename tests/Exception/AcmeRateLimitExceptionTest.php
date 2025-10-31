<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeRateLimitException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * ACME 速率限制异常测试
 *
 * @internal
 */
#[CoversClass(AcmeRateLimitException::class)]
final class AcmeRateLimitExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionExtendsAbstractAcmeException(): void
    {
        $exception = new AcmeRateLimitException();

        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithDefaultParameters(): void
    {
        $exception = new AcmeRateLimitException();

        $this->assertSame('Rate limit exceeded', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertSame('rateLimited', $exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
        $this->assertNull($exception->getRetryAfter());
    }

    public function testExceptionWithCustomMessageAndCode(): void
    {
        $message = 'Too many requests';
        $code = 429;

        $exception = new AcmeRateLimitException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('HTTP error');
        $exception = new AcmeRateLimitException('Rate limit', 429, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithAcmeErrorType(): void
    {
        $errorType = 'rateLimited';
        $exception = new AcmeRateLimitException('Rate limit', 429, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function testExceptionWithAcmeErrorDetails(): void
    {
        $errorDetails = [
            'type' => 'urn:ietf:params:acme:error:rateLimited',
            'detail' => 'Too many requests for this identifier',
            'status' => 429,
        ];

        $exception = new AcmeRateLimitException('Rate limit', 429, null, 'rateLimited', $errorDetails);

        $this->assertSame($errorDetails, $exception->getAcmeErrorDetails());
    }

    public function testExceptionWithRetryAfterDatetime(): void
    {
        $retryAfter = new \DateTimeImmutable('+1 hour');
        $exception = new AcmeRateLimitException('Rate limit', 429, null, 'rateLimited', null, $retryAfter);

        $this->assertSame($retryAfter, $exception->getRetryAfter());
        $this->assertInstanceOf(\DateTimeImmutable::class, $exception->getRetryAfter());
    }

    public function testExceptionWithRetryAfterDatetimeMutable(): void
    {
        $retryAfter = new \DateTimeImmutable('+30 minutes');
        $exception = new AcmeRateLimitException('Rate limit', 429, null, 'rateLimited', null, $retryAfter);

        $this->assertSame($retryAfter, $exception->getRetryAfter());
        $this->assertInstanceOf(\DateTimeImmutable::class, $exception->getRetryAfter());
    }

    public function testExceptionWithAllParameters(): void
    {
        $message = 'Rate limit exceeded for domain validation';
        $code = 429;
        $previous = new \RuntimeException('HTTP 429');
        $acmeErrorType = 'rateLimited';
        $acmeErrorDetails = [
            'type' => 'urn:ietf:params:acme:error:rateLimited',
            'detail' => 'Too many failed validation attempts',
            'status' => 429,
        ];
        $retryAfter = new \DateTimeImmutable('+2 hours');

        $exception = new AcmeRateLimitException(
            $message,
            $code,
            $previous,
            $acmeErrorType,
            $acmeErrorDetails,
            $retryAfter
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($acmeErrorType, $exception->getAcmeErrorType());
        $this->assertSame($acmeErrorDetails, $exception->getAcmeErrorDetails());
        $this->assertSame($retryAfter, $exception->getRetryAfter());
    }

    public function testExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(AcmeRateLimitException::class);
        $this->expectExceptionMessage('Test rate limit');
        $this->expectExceptionCode(429);

        throw new AcmeRateLimitException('Test rate limit', 429);
    }

    public function testExceptionCanBeCaughtAsAbstractAcmeException(): void
    {
        try {
            throw new AcmeRateLimitException('Rate limit test');
        } catch (AbstractAcmeException $e) {
            $this->assertInstanceOf(AcmeRateLimitException::class, $e);
            $this->assertSame('Rate limit test', $e->getMessage());
        }
    }

    /**
     * 测试速率限制异常的业务逻辑场景
     */
    public function testRetryLogicScenario(): void
    {
        $retryAfter = new \DateTimeImmutable('+1 hour');
        $exception = new AcmeRateLimitException(
            'Rate limit exceeded',
            429,
            null,
            'rateLimited',
            ['retry_after' => 3600],
            $retryAfter
        );

        // 验证可以从异常中获取重试时间
        $this->assertNotNull($exception->getRetryAfter());
        $this->assertGreaterThan(new \DateTimeImmutable(), $exception->getRetryAfter());

        // 验证错误详情中包含重试信息
        $details = $exception->getAcmeErrorDetails();
        $this->assertIsArray($details);
        $this->assertArrayHasKey('retry_after', $details);
        $this->assertSame(3600, $details['retry_after']);
    }

    public function testInheritanceHierarchy(): void
    {
        $exception = new AcmeRateLimitException();

        $this->assertInstanceOf(AcmeRateLimitException::class, $exception);
        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }
}
