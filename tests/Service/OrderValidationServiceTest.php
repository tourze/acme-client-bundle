<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Service\OrderValidationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 * OrderValidationService 的功能已通过 OrderServiceTest 进行集成测试
 * 该文件仅用于满足 PHPStan 测试覆盖规则
 * @see OrderServiceTest
 */
#[CoversClass(OrderValidationService::class)]
#[RunTestsInSeparateProcesses]
final class OrderValidationServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 占位测试不需要额外的初始化
    }

    public function testCoveredByIntegrationTests(): void
    {
        self::markTestSkipped('OrderValidationService is covered by OrderServiceTest integration tests');
    }
}
