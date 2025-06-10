<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;

/**
 * ACME 质询状态枚举测试
 */
class ChallengeStatusTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['pending', 'processing', 'valid', 'invalid'];
        $actualCases = array_map(fn(ChallengeStatus $case) => $case->value, ChallengeStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(4, ChallengeStatus::cases());
    }

    public function test_pending_case_properties(): void
    {
        $status = ChallengeStatus::PENDING;

        $this->assertSame('pending', $status->value);
        $this->assertSame('待处理', $status->getLabel());
    }

    public function test_processing_case_properties(): void
    {
        $status = ChallengeStatus::PROCESSING;

        $this->assertSame('processing', $status->value);
        $this->assertSame('处理中', $status->getLabel());
    }

    public function test_valid_case_properties(): void
    {
        $status = ChallengeStatus::VALID;

        $this->assertSame('valid', $status->value);
        $this->assertSame('有效', $status->getLabel());
    }

    public function test_invalid_case_properties(): void
    {
        $status = ChallengeStatus::INVALID;

        $this->assertSame('invalid', $status->value);
        $this->assertSame('无效', $status->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(ChallengeStatus::PENDING, ChallengeStatus::from('pending'));
        $this->assertSame(ChallengeStatus::PROCESSING, ChallengeStatus::from('processing'));
        $this->assertSame(ChallengeStatus::VALID, ChallengeStatus::from('valid'));
        $this->assertSame(ChallengeStatus::INVALID, ChallengeStatus::from('invalid'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        ChallengeStatus::from('unknown_status');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(ChallengeStatus::PENDING, ChallengeStatus::tryFrom('pending'));
        $this->assertSame(ChallengeStatus::PROCESSING, ChallengeStatus::tryFrom('processing'));
        $this->assertSame(ChallengeStatus::VALID, ChallengeStatus::tryFrom('valid'));
        $this->assertSame(ChallengeStatus::INVALID, ChallengeStatus::tryFrom('invalid'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(ChallengeStatus::tryFrom('unknown_status'));
    }

    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(ChallengeStatus::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    public function test_enum_can_be_used_in_array_mapping(): void
    {
        $statusLabels = [];
        foreach (ChallengeStatus::cases() as $status) {
            $statusLabels[$status->value] = $status->getLabel();
        }

        $expected = [
            'pending' => '待处理',
            'processing' => '处理中',
            'valid' => '有效',
            'invalid' => '无效',
        ];

        $this->assertSame($expected, $statusLabels);
    }

    public function test_enum_string_representation(): void
    {
        $this->assertSame('pending', (string) ChallengeStatus::PENDING->value);
        $this->assertSame('processing', (string) ChallengeStatus::PROCESSING->value);
        $this->assertSame('valid', (string) ChallengeStatus::VALID->value);
        $this->assertSame('invalid', (string) ChallengeStatus::INVALID->value);
    }
}
