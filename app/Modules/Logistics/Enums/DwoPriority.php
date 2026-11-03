<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DwoPriority: string
{
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Normal => 'grey',
            self::High   => 'warning',
            self::Urgent => 'error',
        };
    }
}
