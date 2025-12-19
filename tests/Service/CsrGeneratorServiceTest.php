<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Service\CsrGeneratorService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CsrGeneratorService::class)]
#[RunTestsInSeparateProcesses]
final class CsrGeneratorServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 无需设置
    }

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
