<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 日志级别枚举
 */
enum LogLevel: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case DEBUG = 'debug';

    public function getLabel(): string
    {
        return match ($this) {
            self::INFO => '信息',
            self::WARNING => '警告',
            self::ERROR => '错误',
            self::DEBUG => '调试',
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
