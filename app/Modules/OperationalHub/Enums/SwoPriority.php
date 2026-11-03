<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum SwoPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'Low',
            self::Normal => 'Normal',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Low    => 'grey',
            self::Normal => 'info',
            self::High   => 'warning',
            self::Urgent => 'error',
        };
    }
}
