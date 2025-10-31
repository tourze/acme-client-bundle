<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * ACME 质询类型枚举测试
 *
 * @internal
 */
#[CoversClass(ChallengeType::class)]
final class ChallengeTypeTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['dns-01'];
        $actualCases = array_map(fn (ChallengeType $case) => $case->value, ChallengeType::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(1, ChallengeType::cases());
    }

    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(ChallengeType::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    public function testEnumCanBeUsedInArrayMapping(): void
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

    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('dns-01', (string) ChallengeType::DNS_01->value);
    }

    /**
     * 测试是否只支持 DNS-01（符合业务要求）
     */
    public function testOnlyDns01IsSupported(): void
    {
        $allCases = ChallengeType::cases();

        $this->assertCount(1, $allCases);
        $this->assertSame(ChallengeType::DNS_01, $allCases[0]);
        $this->assertSame('dns-01', $allCases[0]->value);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = ChallengeType::toSelectItems();

        $this->assertCount(1, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'dns-01', 'label' => 'DNS-01 质询'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $array = ChallengeType::DNS_01->toArray();
        $this->assertSame(['value' => 'dns-01', 'label' => 'DNS-01 质询'], $array);
    }
}
