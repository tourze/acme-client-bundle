<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * ACME 操作异常测试
 *
 * @internal
 */
#[CoversClass(AcmeOperationException::class)]
final class AcmeOperationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionExtendsAbstractAcmeException(): void
    {
        $exception = new AcmeOperationException();

        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithDefaultParameters(): void
    {
        $exception = new AcmeOperationException();

        $this->assertSame('ACME operation failed', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertSame('operationFailed', $exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
    }

    public function testExceptionWithCustomMessageAndCode(): void
    {
        $message = 'Certificate generation operation failed';
        $code = 503;

        $exception = new AcmeOperationException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('Network connection timeout');
        $exception = new AcmeOperationException('ACME operation failed', 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithAcmeErrorType(): void
    {
        $errorType = 'timeout';
        $exception = new AcmeOperationException('Operation timeout', 408, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function testExceptionWithCustomAcmeErrorType(): void
    {
        $errorType = 'configurationError';
        $exception = new AcmeOperationException('Configuration error', 500, null, $errorType);

        $this->assertSame($errorType, $exception->getAcmeErrorType());
    }

    public function testExceptionWithAcmeErrorDetails(): void
    {
        $errorDetails = [
            'type' => 'urn:ietf:params:acme:error:operationFailed',
            'detail' => 'The ACME operation could not be completed',
            'status' => 500,
        ];

        $exception = new AcmeOperationException('Operation failed', 500, null, 'operationFailed', $errorDetails);

        $this->assertSame($errorDetails, $exception->getAcmeErrorDetails());
    }

    public function testExceptionWithAllParameters(): void
    {
        $message = 'Certificate renewal operation failed';
        $code = 502;
        $previous = new \RuntimeException('External service unavailable');
        $acmeErrorType = 'renewalFailed';
        $acmeErrorDetails = [
            'type' => 'urn:ietf:params:acme:error:renewalFailed',
            'detail' => 'Certificate renewal could not be completed due to external service failure',
            'status' => 502,
            'service' => 'certificate-authority',
        ];

        $exception = new AcmeOperationException(
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
        $this->expectException(AcmeOperationException::class);
        $this->expectExceptionMessage('Test operation error');
        $this->expectExceptionCode(500);

        throw new AcmeOperationException('Test operation error', 500);
    }

    public function testExceptionCanBeCaughtAsAbstractAcmeException(): void
    {
        try {
            throw new AcmeOperationException('Operation error test');
        } catch (AbstractAcmeException $e) {
            $this->assertInstanceOf(AcmeOperationException::class, $e);
            $this->assertSame('Operation error test', $e->getMessage());
        }
    }

    /**
     * 测试不同的操作错误状态码
     */
    public function testDifferentOperationErrorCodes(): void
    {
        $testCases = [
            [500, 'Internal operation error'],
            [502, 'Bad gateway during operation'],
            [503, 'Service unavailable for operation'],
            [504, 'Operation timeout'],
        ];

        foreach ($testCases as [$code, $message]) {
            $exception = new AcmeOperationException($message, $code);

            $this->assertSame($code, $exception->getCode());
            $this->assertSame($message, $exception->getMessage());
            $this->assertInstanceOf(AcmeOperationException::class, $exception);
        }
    }

    /**
     * 测试证书操作失败场景
     */
    public function testCertificateOperationFailureScenario(): void
    {
        $exception = new AcmeOperationException(
            'Certificate issuance operation failed',
            500,
            null,
            'issuanceFailed',
            [
                'detail' => 'Certificate could not be issued due to CA failure',
                'operation' => 'certificate_issuance',
                'ca_response' => 'Temporary system unavailable',
                'retry_after' => 300,
            ]
        );

        // 验证错误详情中包含操作信息
        $details = $exception->getAcmeErrorDetails();
        $this->assertIsArray($details);
        $this->assertArrayHasKey('operation', $details);
        $this->assertSame('certificate_issuance', $details['operation']);
        $this->assertArrayHasKey('retry_after', $details);
        $this->assertSame(300, $details['retry_after']);
    }

    /**
     * 测试域名验证操作失败场景
     */
    public function testDomainValidationOperationFailureScenario(): void
    {
        $exception = new AcmeOperationException(
            'Domain validation operation failed for example.com',
            422,
            null,
            'validationOperationFailed',
            [
                'detail' => 'Challenge validation could not be completed',
                'operation' => 'domain_validation',
                'challenge_type' => 'http-01',
                'domain' => 'example.com',
                'failure_reason' => 'Token file not accessible',
            ]
        );

        $details = $exception->getAcmeErrorDetails();
        $this->assertIsArray($details);
        $this->assertSame('domain_validation', $details['operation']);
        $this->assertSame('http-01', $details['challenge_type']);
        $this->assertSame('example.com', $details['domain']);
        $this->assertStringContainsString('example.com', $exception->getMessage());
    }

    /**
     * 测试账户操作失败场景
     */
    public function testAccountOperationFailureScenario(): void
    {
        $exception = new AcmeOperationException(
            'Account registration operation failed',
            400,
            null,
            'accountOperationFailed',
            [
                'detail' => 'Account could not be registered with provided information',
                'operation' => 'account_registration',
                'validation_errors' => [
                    'email' => 'Invalid email format',
                    'tos' => 'Terms of service not accepted',
                ],
            ]
        );

        $details = $exception->getAcmeErrorDetails();
        $this->assertIsArray($details);
        $this->assertSame('account_registration', $details['operation']);
        $this->assertArrayHasKey('validation_errors', $details);
        $this->assertIsArray($details['validation_errors']);
    }

    public function testInheritanceHierarchy(): void
    {
        $exception = new AcmeOperationException();

        $this->assertInstanceOf(AcmeOperationException::class, $exception);
        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testSerialization(): void
    {
        $details = [
            'type' => 'operationFailed',
            'detail' => 'Test operation error',
            'operation' => 'test_operation',
        ];
        $exception = new AcmeOperationException(
            'Operation failed',
            500,
            null,
            'operationFailed',
            $details
        );

        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(AcmeOperationException::class, $unserialized);
        $this->assertSame('Operation failed', $unserialized->getMessage());
        $this->assertSame(500, $unserialized->getCode());
        $this->assertSame('operationFailed', $unserialized->getAcmeErrorType());
        $this->assertSame($details, $unserialized->getAcmeErrorDetails());
    }

    public function testToStringRepresentation(): void
    {
        $exception = new AcmeOperationException('Operation error', 500);
        $string = (string) $exception;

        $this->assertStringContainsString('AcmeOperationException', $string);
        $this->assertStringContainsString('Operation error', $string);
        $this->assertNotEmpty($string);
    }

    public function testStackTracePreservation(): void
    {
        $exception = new AcmeOperationException('Test operation error');
        $trace = $exception->getTrace();
        $this->assertNotEmpty($trace);
        $this->assertArrayHasKey('function', $trace[0]);
        $this->assertSame(__FUNCTION__, $trace[0]['function']);
    }

    /**
     * 测试异常处理链
     */
    public function testExceptionChain(): void
    {
        $rootCause = new \RuntimeException('Network connection failed');
        $intermediate = new \InvalidArgumentException('Invalid operation parameters', 0, $rootCause);
        $acmeException = new AcmeOperationException('ACME operation failed', 500, $intermediate);

        // 验证异常链
        $this->assertSame($intermediate, $acmeException->getPrevious());
        $this->assertSame($rootCause, $acmeException->getPrevious()->getPrevious());
        $this->assertNull($acmeException->getPrevious()->getPrevious()->getPrevious());
    }

    /**
     * 测试复杂操作失败场景，包含多个子操作
     */
    public function testComplexOperationFailureWithSubOperations(): void
    {
        $exception = new AcmeOperationException(
            'Certificate lifecycle operation failed',
            500,
            null,
            'lifecycleOperationFailed',
            [
                'detail' => 'Multiple sub-operations failed during certificate lifecycle management',
                'operation' => 'certificate_lifecycle',
                'sub_operations' => [
                    [
                        'name' => 'domain_validation',
                        'status' => 'failed',
                        'error' => 'DNS challenge validation timeout',
                    ],
                    [
                        'name' => 'certificate_issuance',
                        'status' => 'skipped',
                        'error' => 'Dependent operation failed',
                    ],
                ],
                'total_operations' => 2,
                'failed_operations' => 1,
                'skipped_operations' => 1,
            ]
        );

        $details = $exception->getAcmeErrorDetails();
        $this->assertIsArray($details);
        $this->assertSame('certificate_lifecycle', $details['operation']);
        $this->assertArrayHasKey('sub_operations', $details);
        $this->assertIsArray($details['sub_operations']);
        $this->assertCount(2, $details['sub_operations']);
        $this->assertSame(1, $details['failed_operations']);
        $this->assertSame(1, $details['skipped_operations']);
    }
}
