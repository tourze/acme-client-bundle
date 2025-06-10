<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;

/**
 * ACME 授权状态枚举测试
 */
class AuthorizationStatusTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['pending', 'valid', 'invalid', 'expired', 'revoked'];
        $actualCases = array_map(fn(AuthorizationStatus $case) => $case->value, AuthorizationStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(5, AuthorizationStatus::cases());
    }

    public function test_pending_case_properties(): void
    {
        $status = AuthorizationStatus::PENDING;

        $this->assertSame('pending', $status->value);
        $this->assertSame('待处理', $status->getLabel());
    }

    public function test_valid_case_properties(): void
    {
        $status = AuthorizationStatus::VALID;

        $this->assertSame('valid', $status->value);
        $this->assertSame('有效', $status->getLabel());
    }

    public function test_invalid_case_properties(): void
    {
        $status = AuthorizationStatus::INVALID;

        $this->assertSame('invalid', $status->value);
        $this->assertSame('无效', $status->getLabel());
    }

    public function test_expired_case_properties(): void
    {
        $status = AuthorizationStatus::EXPIRED;

        $this->assertSame('expired', $status->value);
        $this->assertSame('已过期', $status->getLabel());
    }

    public function test_revoked_case_properties(): void
    {
        $status = AuthorizationStatus::REVOKED;

        $this->assertSame('revoked', $status->value);
        $this->assertSame('已吊销', $status->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(AuthorizationStatus::PENDING, AuthorizationStatus::from('pending'));
        $this->assertSame(AuthorizationStatus::VALID, AuthorizationStatus::from('valid'));
        $this->assertSame(AuthorizationStatus::INVALID, AuthorizationStatus::from('invalid'));
        $this->assertSame(AuthorizationStatus::EXPIRED, AuthorizationStatus::from('expired'));
        $this->assertSame(AuthorizationStatus::REVOKED, AuthorizationStatus::from('revoked'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        AuthorizationStatus::from('unknown_status');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(AuthorizationStatus::PENDING, AuthorizationStatus::tryFrom('pending'));
        $this->assertSame(AuthorizationStatus::VALID, AuthorizationStatus::tryFrom('valid'));
        $this->assertSame(AuthorizationStatus::INVALID, AuthorizationStatus::tryFrom('invalid'));
        $this->assertSame(AuthorizationStatus::EXPIRED, AuthorizationStatus::tryFrom('expired'));
        $this->assertSame(AuthorizationStatus::REVOKED, AuthorizationStatus::tryFrom('revoked'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(AuthorizationStatus::tryFrom('unknown_status'));
    }

    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(AuthorizationStatus::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    public function test_enum_can_be_used_in_array_mapping(): void
    {
        $statusLabels = [];
        foreach (AuthorizationStatus::cases() as $status) {
            $statusLabels[$status->value] = $status->getLabel();
        }

        $expected = [
            'pending' => '待处理',
            'valid' => '有效',
            'invalid' => '无效',
            'expired' => '已过期',
            'revoked' => '已吊销',
        ];

        $this->assertSame($expected, $statusLabels);
    }

    public function test_enum_string_representation(): void
    {
        $this->assertSame('pending', (string) AuthorizationStatus::PENDING->value);
        $this->assertSame('valid', (string) AuthorizationStatus::VALID->value);
        $this->assertSame('invalid', (string) AuthorizationStatus::INVALID->value);
        $this->assertSame('expired', (string) AuthorizationStatus::EXPIRED->value);
        $this->assertSame('revoked', (string) AuthorizationStatus::REVOKED->value);
    }
}
