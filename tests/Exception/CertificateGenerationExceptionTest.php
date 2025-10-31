<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(CertificateGenerationException::class)]
final class CertificateGenerationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionExtendsAbstractAcmeException(): void
    {
        $exception = new CertificateGenerationException('Test message');

        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
    }

    public function testExceptionCanBeCreatedWithMessage(): void
    {
        $message = 'Failed to generate SSL certificate';
        $exception = new CertificateGenerationException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionCanBeCreatedWithMessageAndCode(): void
    {
        $message = 'SSL key generation failed';
        $code = 500;
        $exception = new CertificateGenerationException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionCanBeCreatedWithPreviousException(): void
    {
        $previous = new \RuntimeException('OpenSSL error');
        $exception = new CertificateGenerationException('Certificate generation failed', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
