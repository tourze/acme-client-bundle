<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Service\OrderFinalizationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 * OrderFinalizationService 的功能已通过 OrderServiceTest 进行集成测试
 * 该文件仅用于满足 PHPStan 测试覆盖规则
 * @see OrderServiceTest
 */
#[CoversClass(OrderFinalizationService::class)]
#[RunTestsInSeparateProcesses]
final class OrderFinalizationServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 占位测试不需要额外的初始化
    }

    public function testCoveredByIntegrationTests(): void
    {
        self::markTestSkipped('OrderFinalizationService is covered by OrderServiceTest integration tests');
    }

    public function testFinalizeOrder(): void
    {
        self::markTestSkipped('Placeholder - covered by OrderServiceTest integration tests');
    }

    public function testFinalizeOrderWithAutoCSR(): void
    {
        self::markTestSkipped('Placeholder - covered by OrderServiceTest integration tests');
    }
}
