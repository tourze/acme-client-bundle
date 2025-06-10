<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\ChallengeType;

/**
 * ACME 质询类型枚举测试
 */
class ChallengeTypeTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['dns-01'];
        $actualCases = array_map(fn(ChallengeType $case) => $case->value, ChallengeType::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(1, ChallengeType::cases());
    }

    public function test_dns_01_case_properties(): void
    {
        $type = ChallengeType::DNS_01;

        $this->assertSame('dns-01', $type->value);
        $this->assertSame('DNS-01 质询', $type->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(ChallengeType::DNS_01, ChallengeType::from('dns-01'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        ChallengeType::from('http-01');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(ChallengeType::DNS_01, ChallengeType::tryFrom('dns-01'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(ChallengeType::tryFrom('http-01'));
        $this->assertNull(ChallengeType::tryFrom('tls-alpn-01'));
    }

    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(ChallengeType::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    public function test_enum_can_be_used_in_array_mapping(): void
    {
        $typeLabels = [];
        foreach (ChallengeType::cases() as $type) {
            $typeLabels[$type->value] = $type->getLabel();
        }

        $expected = [
            'dns-01' => 'DNS-01 质询',
        ];

        $this->assertSame($expected, $typeLabels);
    }

    public function test_enum_string_representation(): void
    {
        $this->assertSame('dns-01', (string) ChallengeType::DNS_01->value);
    }

    /**
     * 测试是否只支持 DNS-01（符合业务要求）
     */
    public function test_only_dns_01_is_supported(): void
    {
        $allCases = ChallengeType::cases();

        $this->assertCount(1, $allCases);
        $this->assertSame(ChallengeType::DNS_01, $allCases[0]);
        $this->assertSame('dns-01', $allCases[0]->value);
    }
}
