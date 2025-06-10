<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;

/**
 * AcmeValidationException 测试类
 */
class AcmeValidationExceptionTest extends TestCase
{
    public function test_constructor_withDefaultParameters(): void
    {
        $exception = new AcmeValidationException();

        $this->assertSame('ACME validation failed', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertSame('malformed', $exception->getAcmeErrorType());
        $this->assertNull($exception->getAcmeErrorDetails());
    }

    public function test_constructor_withCustomMessage(): void
    {
        $message = 'Domain validation failed for example.com';
        $exception = new AcmeValidationException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertSame('malformed', $exception->getAcmeErrorType());
    }

    public function test_constructor_withCustomCode(): void
    {
        $exception = new AcmeValidationException('Validation error', 422);

        $this->assertSame('Validation error', $exception->getMessage());
        $this->assertSame(422, $exception->getCode());
    }

    public function test_constructor_withPreviousException(): void
    {
        $previous = new \Exception('Original validation error');
        $exception = new AcmeValidationException('ACME validation failed', 400, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('Original validation error', $exception->getPrevious()->getMessage());
    }

    public function test_constructor_withCustomAcmeErrorType(): void
    {
        $exception = new AcmeValidationException(
            'Invalid domain',
            400,
            null,
            'invalidContact'
        );

        $this->assertSame('invalidContact', $exception->getAcmeErrorType());
    }

    public function test_constructor_withAcmeErrorDetails(): void
    {
        $details = [
            'type' => 'urn:ietf:params:acme:error:malformed',
            'detail' => 'Domain name contains invalid characters',
            'instance' => 'domain-validation-001'
        ];

        $exception = new AcmeValidationException(
            'Domain validation failed',
            400,
            null,
            'malformed',
            $details
        );

        $this->assertSame($details, $exception->getAcmeErrorDetails());
        $this->assertSame('urn:ietf:params:acme:error:malformed', $exception->getAcmeErrorDetails()['type']);
        $this->assertSame('Domain name contains invalid characters', $exception->getAcmeErrorDetails()['detail']);
    }

    public function test_constructor_withAllParameters(): void
    {
        $previous = new \RuntimeException('DNS resolution failed');
        $details = [
            'type' => 'urn:ietf:params:acme:error:dns',
            'detail' => 'DNS TXT record not found',
            'subproblems' => [
                ['type' => 'dns', 'detail' => 'No TXT record found for _acme-challenge.example.com']
            ]
        ];

        $exception = new AcmeValidationException(
            'DNS-01 challenge validation failed',
            400,
            $previous,
            'dns',
            $details
        );

        $this->assertSame('DNS-01 challenge validation failed', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('dns', $exception->getAcmeErrorType());
        $this->assertSame($details, $exception->getAcmeErrorDetails());
    }

    public function test_inheritance_fromAcmeClientException(): void
    {
        $exception = new AcmeValidationException();

        $this->assertInstanceOf(AcmeClientException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function test_validationScenarios_domainValidationFailure(): void
    {
        $details = [
            'type' => 'urn:ietf:params:acme:error:unauthorized',
            'detail' => 'Domain control validation for example.com failed',
            'status' => 403
        ];

        $exception = new AcmeValidationException(
            'Domain control validation failed for example.com',
            403,
            null,
            'unauthorized',
            $details
        );

        $this->assertSame('unauthorized', $exception->getAcmeErrorType());
        $this->assertSame(403, $exception->getCode());
        $this->assertStringContainsString('example.com', $exception->getMessage());
    }

    public function test_validationScenarios_challengeValidationFailure(): void
    {
        $details = [
            'type' => 'urn:ietf:params:acme:error:incorrectResponse',
            'detail' => 'Key authorization was incorrect',
            'token' => 'abc123token',
            'keyAuthorization' => 'expected_value'
        ];

        $exception = new AcmeValidationException(
            'Challenge validation failed: incorrect response',
            400,
            null,
            'incorrectResponse',
            $details
        );

        $this->assertSame('incorrectResponse', $exception->getAcmeErrorType());
        $this->assertArrayHasKey('token', $exception->getAcmeErrorDetails());
        $this->assertArrayHasKey('keyAuthorization', $exception->getAcmeErrorDetails());
    }

    public function test_validationScenarios_csrValidationFailure(): void
    {
        $details = [
            'type' => 'urn:ietf:params:acme:error:badCSR',
            'detail' => 'Certificate signing request contains invalid data',
            'field' => 'subject'
        ];

        $exception = new AcmeValidationException(
            'CSR validation failed',
            400,
            null,
            'badCSR',
            $details
        );

        $this->assertSame('badCSR', $exception->getAcmeErrorType());
        $this->assertStringContainsString('CSR', $exception->getMessage());
        $this->assertSame('subject', $exception->getAcmeErrorDetails()['field']);
    }

    public function test_serialization(): void
    {
        $details = ['type' => 'malformed', 'detail' => 'Test validation error'];
        $exception = new AcmeValidationException(
            'Validation failed',
            400,
            null,
            'malformed',
            $details
        );

        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(AcmeValidationException::class, $unserialized);
        $this->assertSame('Validation failed', $unserialized->getMessage());
        $this->assertSame(400, $unserialized->getCode());
        $this->assertSame('malformed', $unserialized->getAcmeErrorType());
        $this->assertSame($details, $unserialized->getAcmeErrorDetails());
    }

    public function test_toString_representation(): void
    {
        $exception = new AcmeValidationException('Domain validation error', 400);
        $string = (string) $exception;

        $this->assertStringContainsString('AcmeValidationException', $string);
        $this->assertStringContainsString('Domain validation error', $string);
        // Note: Exception string representation includes message and file info, but not always the code
        $this->assertIsString($string);
        $this->assertNotEmpty($string);
    }

    public function test_stackTrace_preservation(): void
    {
        $exception = new AcmeValidationException('Test validation error');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertNotEmpty($trace);
        $this->assertArrayHasKey('function', $trace[0]);
        $this->assertSame(__FUNCTION__, $trace[0]['function']);
    }
}
