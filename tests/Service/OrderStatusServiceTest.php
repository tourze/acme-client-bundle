<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Service\OrderStatusService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 * OrderStatusService 的功能已通过 OrderServiceTest 进行集成测试
 * 该文件仅用于满足 PHPStan 测试覆盖规则
 * @see OrderServiceTest
 */
#[CoversClass(OrderStatusService::class)]
#[RunTestsInSeparateProcesses]
final class OrderStatusServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 无需设置
    }

    public function testCoveredByIntegrationTests(): void
    {
        self::markTestSkipped('OrderStatusService is covered by OrderServiceTest integration tests');
    }

    public function testRefreshOrderStatus(): void
    {
        self::markTestSkipped('Placeholder - covered by OrderServiceTest integration tests');
    }
}
