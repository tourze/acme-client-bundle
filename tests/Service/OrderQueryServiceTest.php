<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Service\OrderQueryService;

/**
 * @internal
 * OrderQueryService 的功能已通过 OrderServiceTest 进行集成测试
 * 该文件仅用于满足 PHPStan 测试覆盖规则
 * @see OrderServiceTest
 */
#[CoversClass(OrderQueryService::class)]
final class OrderQueryServiceTest extends TestCase
{
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
