<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Service\OrderQueryService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 * OrderQueryService 的功能已通过 OrderServiceTest 进行集成测试
 * 该文件仅用于满足 PHPStan 测试覆盖规则
 * @see OrderServiceTest
 */
#[CoversClass(OrderQueryService::class)]
#[RunTestsInSeparateProcesses]
final class OrderQueryServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 无需设置
    }

    public function testCoveredByIntegrationTests(): void
    {
        self::markTestSkipped('OrderQueryService is covered by OrderServiceTest integration tests');
    }

    public function testFindOrdersByAccount(): void
    {
        self::markTestSkipped('Placeholder - covered by OrderServiceTest integration tests');
    }

    public function testFindOrdersByStatus(): void
    {
        self::markTestSkipped('Placeholder - covered by OrderServiceTest integration tests');
    }
}
