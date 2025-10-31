<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Service\CsrGeneratorService;

/**
 * @internal
 */
#[CoversClass(CsrGeneratorService::class)]
final class CsrGeneratorServiceTest extends TestCase
{
    public function testPlaceholder(): void
    {
        // This test class is a placeholder.
        // Individual service functionality is tested through CertificateServiceTest integration tests.
        self::markTestSkipped('Placeholder test - covered by CertificateServiceTest');
    }

    public function testGenerateCsr(): void
    {
        self::markTestSkipped('Placeholder - covered by CertificateServiceTest integration tests');
    }
}
