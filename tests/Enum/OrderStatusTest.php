<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\OrderStatus;

/**
 * ACME 订单状态枚举测试
 */
class OrderStatusTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['pending', 'ready', 'processing', 'valid', 'invalid'];
        $actualCases = array_map(fn(OrderStatus $case) => $case->value, OrderStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(5, OrderStatus::cases());
    }

    public function test_pending_case_properties(): void
    {
        $status = OrderStatus::PENDING;

        $this->assertSame('pending', $status->value);
        $this->assertSame('待处理', $status->getLabel());
    }

    public function test_ready_case_properties(): void
    {
        $status = OrderStatus::READY;

        $this->assertSame('ready', $status->value);
        $this->assertSame('准备就绪', $status->getLabel());
    }

    public function test_processing_case_properties(): void
    {
        $status = OrderStatus::PROCESSING;

        $this->assertSame('processing', $status->value);
        $this->assertSame('处理中', $status->getLabel());
    }

    public function test_valid_case_properties(): void
    {
        $status = OrderStatus::VALID;

        $this->assertSame('valid', $status->value);
        $this->assertSame('有效', $status->getLabel());
    }

    public function test_invalid_case_properties(): void
    {
        $status = OrderStatus::INVALID;

        $this->assertSame('invalid', $status->value);
        $this->assertSame('无效', $status->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(OrderStatus::PENDING, OrderStatus::from('pending'));
        $this->assertSame(OrderStatus::READY, OrderStatus::from('ready'));
        $this->assertSame(OrderStatus::PROCESSING, OrderStatus::from('processing'));
        $this->assertSame(OrderStatus::VALID, OrderStatus::from('valid'));
        $this->assertSame(OrderStatus::INVALID, OrderStatus::from('invalid'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        OrderStatus::from('unknown_status');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(OrderStatus::PENDING, OrderStatus::tryFrom('pending'));
        $this->assertSame(OrderStatus::READY, OrderStatus::tryFrom('ready'));
        $this->assertSame(OrderStatus::PROCESSING, OrderStatus::tryFrom('processing'));
        $this->assertSame(OrderStatus::VALID, OrderStatus::tryFrom('valid'));
        $this->assertSame(OrderStatus::INVALID, OrderStatus::tryFrom('invalid'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(OrderStatus::tryFrom('unknown_status'));
    }

    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(OrderStatus::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    public function test_enum_can_be_used_in_array_mapping(): void
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

    public function test_enum_string_representation(): void
    {
        $this->assertSame('pending', (string) OrderStatus::PENDING->value);
        $this->assertSame('ready', (string) OrderStatus::READY->value);
        $this->assertSame('processing', (string) OrderStatus::PROCESSING->value);
        $this->assertSame('valid', (string) OrderStatus::VALID->value);
        $this->assertSame('invalid', (string) OrderStatus::INVALID->value);
    }
}
