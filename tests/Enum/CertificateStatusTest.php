<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * ACME 证书状态枚举测试
 *
 * @internal
 */
#[CoversClass(CertificateStatus::class)]
final class CertificateStatusTest extends AbstractEnumTestCase
{
    public function testEnumHasAllExpectedCases(): void
    {
        $expectedCases = ['valid', 'expired', 'revoked', 'issued'];
        $actualCases = array_map(fn (CertificateStatus $case) => $case->value, CertificateStatus::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(4, CertificateStatus::cases());
    }

    public function testEnumImplementsRequiredInterfaces(): void
    {
        $reflection = new \ReflectionEnum(CertificateStatus::class);

        $this->assertTrue($reflection->implementsInterface(Labelable::class));
        $this->assertTrue($reflection->implementsInterface(Itemable::class));
        $this->assertTrue($reflection->implementsInterface(Selectable::class));
    }

    public function testEnumCanBeUsedInArrayMapping(): void
    {
        $statusLabels = [];
        foreach (CertificateStatus::cases() as $status) {
            $statusLabels[$status->value] = $status->getLabel();
        }

        $expected = [
            'valid' => '有效',
            'expired' => '已过期',
            'revoked' => '已吊销',
            'issued' => '已签发',
        ];

        $this->assertSame($expected, $statusLabels);
    }

    public function testEnumStringRepresentation(): void
    {
        $this->assertSame('valid', (string) CertificateStatus::VALID->value);
        $this->assertSame('expired', (string) CertificateStatus::EXPIRED->value);
        $this->assertSame('revoked', (string) CertificateStatus::REVOKED->value);
    }

    /**
     * 测试证书状态的业务逻辑（虽然逻辑在 Entity 中，但可以验证枚举的完整性）
     */
    public function testNoPendingStatusForCertificates(): void
    {
        // 证书不应该有 pending 状态，因为证书要么有效、过期或撤销
        $cases = CertificateStatus::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        $this->assertNotContains('pending', $values);
        $this->assertNotContains('processing', $values);
    }

    /**
     * 测试 toSelectItems 方法
     */
    public function testToSelectItems(): void
    {
        $selectItems = CertificateStatus::toSelectItems();

        $this->assertCount(4, $selectItems);

        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }

        // 验证具体内容
        $this->assertContains(['value' => 'valid', 'label' => '有效'], $selectItems);
        $this->assertContains(['value' => 'expired', 'label' => '已过期'], $selectItems);
        $this->assertContains(['value' => 'revoked', 'label' => '已吊销'], $selectItems);
        $this->assertContains(['value' => 'issued', 'label' => '已签发'], $selectItems);
    }

    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $validArray = CertificateStatus::VALID->toArray();
        $this->assertSame(['value' => 'valid', 'label' => '有效'], $validArray);

        $expiredArray = CertificateStatus::EXPIRED->toArray();
        $this->assertSame(['value' => 'expired', 'label' => '已过期'], $expiredArray);

        $revokedArray = CertificateStatus::REVOKED->toArray();
        $this->assertSame(['value' => 'revoked', 'label' => '已吊销'], $revokedArray);

        $issuedArray = CertificateStatus::ISSUED->toArray();
        $this->assertSame(['value' => 'issued', 'label' => '已签发'], $issuedArray);
    }
}
