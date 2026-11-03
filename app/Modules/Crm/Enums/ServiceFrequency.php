<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum ServiceFrequency: string
{
    case OneTime = 'one_time';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case BiWeekly = 'bi_weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Annually = 'annually';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::BiWeekly => 'Bi-weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnually => 'Semi-annually',
            self::Annually => 'Annually',
            self::Custom => 'Custom',
        };
    }

    public function toInterval(): ?string
    {
        return match ($this) {
            self::OneTime => null,
            self::Daily => 'P1D',
            self::Weekly => 'P1W',
            self::BiWeekly => 'P2W',
            self::Monthly => 'P1M',
            self::Quarterly => 'P3M',
            self::SemiAnnually => 'P6M',
            self::Annually => 'P1Y',
            self::Custom => null,
        };
    }
}