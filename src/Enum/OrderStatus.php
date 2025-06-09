<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * ACME 订单状态枚举
 */
enum OrderStatus: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case READY = 'ready';
    case PROCESSING = 'processing';
    case VALID = 'valid';
    case INVALID = 'invalid';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::READY => '准备就绪',
            self::PROCESSING => '处理中',
            self::VALID => '有效',
            self::INVALID => '无效',
        };
    }
}
