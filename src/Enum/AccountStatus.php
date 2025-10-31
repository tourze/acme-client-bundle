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
 * ACME 账户状态枚举
 */
enum AccountStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case VALID = 'valid';
    case DEACTIVATED = 'deactivated';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::VALID => '有效',
            self::DEACTIVATED => '已停用',
        };
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::PENDING => BadgeInterface::WARNING,
            self::VALID => BadgeInterface::SUCCESS,
            self::DEACTIVATED => BadgeInterface::SECONDARY,
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
