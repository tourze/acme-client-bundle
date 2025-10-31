<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Service\CertificateValidationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CertificateValidationService::class)]
#[RunTestsInSeparateProcesses]
final class CertificateValidationServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 占位测试不需要额外的初始化
    }

    public function testPlaceholder(): void
    {
        // This test class is a placeholder.
        // Individual service functionality is tested through CertificateServiceTest integration tests.
        self::markTestSkipped('Placeholder test - covered by CertificateServiceTest');
    }

    public function testValidateCertificate(): void
    {
        self::markTestSkipped('Placeholder - covered by CertificateServiceTest integration tests');
    }
}
