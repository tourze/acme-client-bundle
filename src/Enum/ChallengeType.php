<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * ACME 质询类型枚举
 */
enum ChallengeType: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case DNS_01 = 'dns-01';

    public function getLabel(): string
    {
        return match ($this) {
            self::DNS_01 => 'DNS-01 质询',
        };
    }
}
