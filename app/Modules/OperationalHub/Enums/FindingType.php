<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum FindingType: string
{
    case Conformity     = 'conformity';
    case NonConformity  = 'non_conformity';
    case Observation    = 'observation';
    case Recommendation = 'recommendation';

    public function label(): string
    {
        return match ($this) {
            self::Conformity     => 'Conformity',
            self::NonConformity  => 'Non-Conformity',
            self::Observation    => 'Observation',
            self::Recommendation => 'Recommendation',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Conformity     => 'success',
            self::NonConformity  => 'error',
            self::Observation    => 'info',
            self::Recommendation => 'warning',
        };
    }
}
