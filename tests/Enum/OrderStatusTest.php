<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * ACME 订单状态枚举测试
 *
 * @internal
 */
#[CoversClass(OrderStatus::class)]
final class OrderStatusTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['pending', 'ready', 'processing', 'valid', 'invalid'];
        $actualCases = array_map(fn (OrderStatus $case) => $case->value, OrderStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(5, OrderStatus::cases());
    }

    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(OrderStatus::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    public function testEnumCanBeUsedInArrayMapping(): void
    {
        $statusLabels = [];
        foreach (OrderStatus::cases() as $status) {
            $statusLabels[$status->value] = $status->getLabel();
        }

        $expected = [
            'pending' => '待处理',
            'ready' => '准备就绪',
            'processing' => '处理中',
            'valid' => '有效',
            'invalid' => '无效',
        ];

        $this->assertSame($expected, $statusLabels);
    }

    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('pending', (string) OrderStatus::PENDING->value);
        $this->assertSame('ready', (string) OrderStatus::READY->value);
        $this->assertSame('processing', (string) OrderStatus::PROCESSING->value);
        $this->assertSame('valid', (string) OrderStatus::VALID->value);
        $this->assertSame('invalid', (string) OrderStatus::INVALID->value);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = OrderStatus::toSelectItems();

        $this->assertCount(5, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'pending', 'label' => '待处理'], $selectItems);
        $this->assertContains(['value' => 'ready', 'label' => '准备就绪'], $selectItems);
        $this->assertContains(['value' => 'processing', 'label' => '处理中'], $selectItems);
        $this->assertContains(['value' => 'valid', 'label' => '有效'], $selectItems);
        $this->assertContains(['value' => 'invalid', 'label' => '无效'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $array = OrderStatus::PENDING->toArray();
        $this->assertSame(['value' => 'pending', 'label' => '待处理'], $array);

        $array = OrderStatus::READY->toArray();
        $this->assertSame(['value' => 'ready', 'label' => '准备就绪'], $array);

        $array = OrderStatus::PROCESSING->toArray();
        $this->assertSame(['value' => 'processing', 'label' => '处理中'], $array);

        $array = OrderStatus::VALID->toArray();
        $this->assertSame(['value' => 'valid', 'label' => '有效'], $array);

        $array = OrderStatus::INVALID->toArray();
        $this->assertSame(['value' => 'invalid', 'label' => '无效'], $array);
    }
}
