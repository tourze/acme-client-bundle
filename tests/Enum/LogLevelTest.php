<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Enum\LogLevel;

/**
 * 日志级别枚举测试
 */
class LogLevelTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $expectedCases = ['info', 'warning', 'error', 'debug'];
        $actualCases = array_map(fn(LogLevel $case) => $case->value, LogLevel::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(4, LogLevel::cases());
    }

    public function test_info_case_properties(): void
    {
        $level = LogLevel::INFO;

        $this->assertSame('info', $level->value);
        $this->assertSame('信息', $level->getLabel());
    }

    public function test_warning_case_properties(): void
    {
        $level = LogLevel::WARNING;

        $this->assertSame('warning', $level->value);
        $this->assertSame('警告', $level->getLabel());
    }

    public function test_error_case_properties(): void
    {
        $level = LogLevel::ERROR;

        $this->assertSame('error', $level->value);
        $this->assertSame('错误', $level->getLabel());
    }

    public function test_debug_case_properties(): void
    {
        $level = LogLevel::DEBUG;

        $this->assertSame('debug', $level->value);
        $this->assertSame('调试', $level->getLabel());
    }

    public function test_enum_from_string_value(): void
    {
        $this->assertSame(LogLevel::INFO, LogLevel::from('info'));
        $this->assertSame(LogLevel::WARNING, LogLevel::from('warning'));
        $this->assertSame(LogLevel::ERROR, LogLevel::from('error'));
        $this->assertSame(LogLevel::DEBUG, LogLevel::from('debug'));
    }

    public function test_enum_from_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        LogLevel::from('critical');
    }

    public function test_try_from_with_valid_values(): void
    {
        $this->assertSame(LogLevel::INFO, LogLevel::tryFrom('info'));
        $this->assertSame(LogLevel::WARNING, LogLevel::tryFrom('warning'));
        $this->assertSame(LogLevel::ERROR, LogLevel::tryFrom('error'));
        $this->assertSame(LogLevel::DEBUG, LogLevel::tryFrom('debug'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $this->assertNull(LogLevel::tryFrom('critical'));
        $this->assertNull(LogLevel::tryFrom('notice'));
    }

    public function test_enum_implements_required_interfaces(): void
    {
        $reflection = new \ReflectionEnum(LogLevel::class);

        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }

    public function test_enum_can_be_used_in_array_mapping(): void
    {
        $levelLabels = [];
        foreach (LogLevel::cases() as $level) {
            $levelLabels[$level->value] = $level->getLabel();
        }

        $expected = [
            'info' => '信息',
            'warning' => '警告',
            'error' => '错误',
            'debug' => '调试',
        ];

        $this->assertSame($expected, $levelLabels);
    }

    public function test_enum_string_representation(): void
    {
        $this->assertSame('info', (string) LogLevel::INFO->value);
        $this->assertSame('warning', (string) LogLevel::WARNING->value);
        $this->assertSame('error', (string) LogLevel::ERROR->value);
        $this->assertSame('debug', (string) LogLevel::DEBUG->value);
    }

    /**
     * 测试日志级别的优先级（虽然枚举本身不包含优先级逻辑，但可以测试顺序）
     */
    public function test_log_level_order(): void
    {
        $cases = LogLevel::cases();

        // 验证枚举定义的顺序
        $this->assertSame(LogLevel::INFO, $cases[0]);
        $this->assertSame(LogLevel::WARNING, $cases[1]);
        $this->assertSame(LogLevel::ERROR, $cases[2]);
        $this->assertSame(LogLevel::DEBUG, $cases[3]);
    }
}
