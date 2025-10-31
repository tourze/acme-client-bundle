<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * 日志级别枚举测试
 *
 * @internal
 */
#[CoversClass(LogLevel::class)]
final class LogLevelTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['info', 'warning', 'error', 'debug'];
        $actualCases = array_map(fn (LogLevel $case) => $case->value, LogLevel::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(4, LogLevel::cases());
    }

    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(LogLevel::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    public function testEnumCanBeUsedInArrayMapping(): void
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

    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('info', (string) LogLevel::INFO->value);
        $this->assertSame('warning', (string) LogLevel::WARNING->value);
        $this->assertSame('error', (string) LogLevel::ERROR->value);
        $this->assertSame('debug', (string) LogLevel::DEBUG->value);
    }

    /**
     * 测试日志级别的优先级（虽然枚举本身不包含优先级逻辑，但可以测试顺序）
     */
    public function testLogLevelOrder(): void
    {
        $cases = LogLevel::cases();

        // 验证枚举定义的顺序
        $this->assertSame(LogLevel::INFO, $cases[0]);
        $this->assertSame(LogLevel::WARNING, $cases[1]);
        $this->assertSame(LogLevel::ERROR, $cases[2]);
        $this->assertSame(LogLevel::DEBUG, $cases[3]);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = LogLevel::toSelectItems();

        $this->assertCount(4, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'info', 'label' => '信息'], $selectItems);
        $this->assertContains(['value' => 'warning', 'label' => '警告'], $selectItems);
        $this->assertContains(['value' => 'error', 'label' => '错误'], $selectItems);
        $this->assertContains(['value' => 'debug', 'label' => '调试'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $array = LogLevel::INFO->toArray();
        $this->assertSame(['value' => 'info', 'label' => '信息'], $array);

        $array = LogLevel::WARNING->toArray();
        $this->assertSame(['value' => 'warning', 'label' => '警告'], $array);

        $array = LogLevel::ERROR->toArray();
        $this->assertSame(['value' => 'error', 'label' => '错误'], $array);

        $array = LogLevel::DEBUG->toArray();
        $this->assertSame(['value' => 'debug', 'label' => '调试'], $array);
    }
}
