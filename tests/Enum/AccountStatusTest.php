<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * ACME 账户状态枚举测试
 *
 * @internal
 */
#[CoversClass(AccountStatus::class)]
final class AccountStatusTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['pending', 'valid', 'deactivated'];
        $actualCases = array_map(fn (AccountStatus $case) => $case->value, AccountStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(3, AccountStatus::cases());
    }

    /**
     * 测试枚举是否实现了所需的接口
     */
    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(AccountStatus::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    /**
     * 测试枚举可以用于数组映射
     */
    public function testEnumCanBeUsedInArrayMapping(): void
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
    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('pending', (string) AccountStatus::PENDING->value);
        $this->assertSame('valid', (string) AccountStatus::VALID->value);
        $this->assertSame('deactivated', (string) AccountStatus::DEACTIVATED->value);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = AccountStatus::toSelectItems();

        $this->assertCount(3, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'pending', 'label' => '待处理'], $selectItems);
        $this->assertContains(['value' => 'valid', 'label' => '有效'], $selectItems);
        $this->assertContains(['value' => 'deactivated', 'label' => '已停用'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $pendingArray = AccountStatus::PENDING->toArray();
        $this->assertSame(['value' => 'pending', 'label' => '待处理'], $pendingArray);

        $validArray = AccountStatus::VALID->toArray();
        $this->assertSame(['value' => 'valid', 'label' => '有效'], $validArray);

        $deactivatedArray = AccountStatus::DEACTIVATED->toArray();
        $this->assertSame(['value' => 'deactivated', 'label' => '已停用'], $deactivatedArray);
    }
}
