<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * ACME 授权状态枚举测试
 *
 * @internal
 */
#[CoversClass(AuthorizationStatus::class)]
final class AuthorizationStatusTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['pending', 'valid', 'invalid', 'expired', 'revoked'];
        $actualCases = array_map(fn (AuthorizationStatus $case) => $case->value, AuthorizationStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(5, AuthorizationStatus::cases());
    }

    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(AuthorizationStatus::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    public function testEnumCanBeUsedInArrayMapping(): void
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

    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('pending', (string) AuthorizationStatus::PENDING->value);
        $this->assertSame('valid', (string) AuthorizationStatus::VALID->value);
        $this->assertSame('invalid', (string) AuthorizationStatus::INVALID->value);
        $this->assertSame('expired', (string) AuthorizationStatus::EXPIRED->value);
        $this->assertSame('revoked', (string) AuthorizationStatus::REVOKED->value);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = AuthorizationStatus::toSelectItems();

        $this->assertCount(5, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'pending', 'label' => '待处理'], $selectItems);
        $this->assertContains(['value' => 'valid', 'label' => '有效'], $selectItems);
        $this->assertContains(['value' => 'invalid', 'label' => '无效'], $selectItems);
        $this->assertContains(['value' => 'expired', 'label' => '已过期'], $selectItems);
        $this->assertContains(['value' => 'revoked', 'label' => '已吊销'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $pendingArray = AuthorizationStatus::PENDING->toArray();
        $this->assertSame(['value' => 'pending', 'label' => '待处理'], $pendingArray);

        $validArray = AuthorizationStatus::VALID->toArray();
        $this->assertSame(['value' => 'valid', 'label' => '有效'], $validArray);

        $invalidArray = AuthorizationStatus::INVALID->toArray();
        $this->assertSame(['value' => 'invalid', 'label' => '无效'], $invalidArray);

        $expiredArray = AuthorizationStatus::EXPIRED->toArray();
        $this->assertSame(['value' => 'expired', 'label' => '已过期'], $expiredArray);

        $revokedArray = AuthorizationStatus::REVOKED->toArray();
        $this->assertSame(['value' => 'revoked', 'label' => '已吊销'], $revokedArray);
    }
}
