<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * ACME 质询状态枚举测试
 *
 * @internal
 */
#[CoversClass(ChallengeStatus::class)]
final class ChallengeStatusTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['pending', 'processing', 'valid', 'invalid'];
        $actualCases = array_map(fn (ChallengeStatus $case) => $case->value, ChallengeStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(4, ChallengeStatus::cases());
    }

    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(ChallengeStatus::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    public function testEnumCanBeUsedInArrayMapping(): void
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

    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('pending', (string) ChallengeStatus::PENDING->value);
        $this->assertSame('processing', (string) ChallengeStatus::PROCESSING->value);
        $this->assertSame('valid', (string) ChallengeStatus::VALID->value);
        $this->assertSame('invalid', (string) ChallengeStatus::INVALID->value);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = ChallengeStatus::toSelectItems();

        $this->assertCount(4, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'pending', 'label' => '待处理'], $selectItems);
        $this->assertContains(['value' => 'processing', 'label' => '处理中'], $selectItems);
        $this->assertContains(['value' => 'valid', 'label' => '有效'], $selectItems);
        $this->assertContains(['value' => 'invalid', 'label' => '无效'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $array = ChallengeStatus::PENDING->toArray();
        $this->assertSame(['value' => 'pending', 'label' => '待处理'], $array);

        $array = ChallengeStatus::PROCESSING->toArray();
        $this->assertSame(['value' => 'processing', 'label' => '处理中'], $array);

        $array = ChallengeStatus::VALID->toArray();
        $this->assertSame(['value' => 'valid', 'label' => '有效'], $array);

        $array = ChallengeStatus::INVALID->toArray();
        $this->assertSame(['value' => 'invalid', 'label' => '无效'], $array);
    }
}
