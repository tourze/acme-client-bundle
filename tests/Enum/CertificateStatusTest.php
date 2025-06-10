<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;

/**
 * ACME 证书状态枚举测试
 */
class CertificateStatusTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['valid', 'expired', 'revoked'];
        $actualCases = array_map(fn(CertificateStatus $case) => $case->value, CertificateStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(3, CertificateStatus::cases());
    }

    public function test_valid_case_properties(): void
    {
        $status = CertificateStatus::VALID;

        $this->assertSame('valid', $status->value);
        $this->assertSame('有效', $status->getLabel());
    }

    public function test_expired_case_properties(): void
    {
        $status = CertificateStatus::EXPIRED;

        $this->assertSame('expired', $status->value);
        $this->assertSame('已过期', $status->getLabel());
    }

    public function test_revoked_case_properties(): void
    {
        $status = CertificateStatus::REVOKED;

        $this->assertSame('revoked', $status->value);
        $this->assertSame('已吊销', $status->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(CertificateStatus::VALID, CertificateStatus::from('valid'));
        $this->assertSame(CertificateStatus::EXPIRED, CertificateStatus::from('expired'));
        $this->assertSame(CertificateStatus::REVOKED, CertificateStatus::from('revoked'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        CertificateStatus::from('pending');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(CertificateStatus::VALID, CertificateStatus::tryFrom('valid'));
        $this->assertSame(CertificateStatus::EXPIRED, CertificateStatus::tryFrom('expired'));
        $this->assertSame(CertificateStatus::REVOKED, CertificateStatus::tryFrom('revoked'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(CertificateStatus::tryFrom('pending'));
        $this->assertNull(CertificateStatus::tryFrom('processing'));
    }

    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(CertificateStatus::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    public function test_enum_can_be_used_in_array_mapping(): void
    {
        $statusLabels = [];
        foreach (CertificateStatus::cases() as $status) {
            $statusLabels[$status->value] = $status->getLabel();
        }

        $expected = [
            'valid' => '有效',
            'expired' => '已过期',
            'revoked' => '已吊销',
        ];

        $this->assertSame($expected, $statusLabels);
    }

    public function test_enum_string_representation(): void
    {
        $this->assertSame('valid', (string) CertificateStatus::VALID->value);
        $this->assertSame('expired', (string) CertificateStatus::EXPIRED->value);
        $this->assertSame('revoked', (string) CertificateStatus::REVOKED->value);
    }

    /**
     * 测试证书状态的业务逻辑（虽然逻辑在 Entity 中，但可以验证枚举的完整性）
     */
    public function test_no_pending_status_for_certificates(): void
    {
        // 证书不应该有 pending 状态，因为证书要么有效、过期或撤销
        $cases = CertificateStatus::cases();
        $values = array_map(fn($case) => $case->value, $cases);

        $this->assertNotContains('pending', $values);
        $this->assertNotContains('processing', $values);
    }
}
