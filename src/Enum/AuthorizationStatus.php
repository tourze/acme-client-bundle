<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * ACME 授权状态枚举
 */
enum AuthorizationStatus: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case VALID = 'valid';
    case INVALID = 'invalid';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::VALID => '有效',
            self::INVALID => '无效',
            self::EXPIRED => '已过期',
            self::REVOKED => '已吊销',
        };
    }
}
