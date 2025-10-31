<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * ACME 证书状态枚举
 */
enum CertificateStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;

    case VALID = 'valid';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
    case ISSUED = 'issued';

    public function getLabel(): string
    {
        return match ($this) {
            self::VALID => '有效',
            self::EXPIRED => '已过期',
            self::REVOKED => '已吊销',
            self::ISSUED => '已签发',
        };
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::VALID => BadgeInterface::SUCCESS,
            self::EXPIRED => BadgeInterface::WARNING,
            self::REVOKED => BadgeInterface::DANGER,
            self::ISSUED => BadgeInterface::INFO,
        };
    }

    /**
     * 获取所有枚举的选项数组（用于下拉列表等）
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function toSelectItems(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
            ];
        }

        return $result;
    }
}
