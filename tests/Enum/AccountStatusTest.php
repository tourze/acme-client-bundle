<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\AccountStatus;

/**
 * ACME 账户状态枚举测试
 */
class AccountStatusTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['pending', 'valid', 'deactivated'];
        $actualCases = array_map(fn(AccountStatus $case) => $case->value, AccountStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(3, AccountStatus::cases());
    }

    public function test_pending_case_properties(): void
    {
        $status = AccountStatus::PENDING;

        $this->assertSame('pending', $status->value);
        $this->assertSame('待处理', $status->getLabel());
    }

    public function test_valid_case_properties(): void
    {
        $status = AccountStatus::VALID;

        $this->assertSame('valid', $status->value);
        $this->assertSame('有效', $status->getLabel());
    }

    public function test_deactivated_case_properties(): void
    {
        $status = AccountStatus::DEACTIVATED;

        $this->assertSame('deactivated', $status->value);
        $this->assertSame('已停用', $status->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(AccountStatus::PENDING, AccountStatus::from('pending'));
        $this->assertSame(AccountStatus::VALID, AccountStatus::from('valid'));
        $this->assertSame(AccountStatus::DEACTIVATED, AccountStatus::from('deactivated'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        AccountStatus::from('invalid_status');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(AccountStatus::PENDING, AccountStatus::tryFrom('pending'));
        $this->assertSame(AccountStatus::VALID, AccountStatus::tryFrom('valid'));
        $this->assertSame(AccountStatus::DEACTIVATED, AccountStatus::tryFrom('deactivated'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(AccountStatus::tryFrom('invalid_status'));
    }

    /**
     * 测试枚举是否实现了所需的接口
     */
    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(AccountStatus::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    /**
     * 测试枚举可以用于数组映射
     */
    public function test_enum_can_be_used_in_array_mapping(): void
    {
        $statusLabels = [];
        foreach (AccountStatus::cases() as $status) {
            $statusLabels[$status->value] = $status->getLabel();
        }

        $expected = [
            'pending' => '待处理',
            'valid' => '有效',
            'deactivated' => '已停用',
        ];

        $this->assertSame($expected, $statusLabels);
    }

    /**
     * 测试枚举的字符串表示
     */
    public function test_enum_string_representation(): void
    {
        $this->assertSame('pending', (string) AccountStatus::PENDING->value);
        $this->assertSame('valid', (string) AccountStatus::VALID->value);
        $this->assertSame('deactivated', (string) AccountStatus::DEACTIVATED->value);
    }
}
