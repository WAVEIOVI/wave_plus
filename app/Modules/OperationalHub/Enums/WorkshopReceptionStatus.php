<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum WorkshopReceptionStatus: string
{
    case Pending     = 'pending';
    case Received    = 'received';
    case InDiagnosis = 'in_diagnosis';
    case InProgress  = 'in_progress';
    case Testing     = 'testing';
    case Completed   = 'completed';
    case Returned    = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending     => 'Pending',
            self::Received    => 'Received',
            self::InDiagnosis => 'In Diagnosis',
            self::InProgress  => 'In Progress',
            self::Testing     => 'Testing',
            self::Completed   => 'Completed',
            self::Returned    => 'Returned',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending     => 'grey',
            self::Received    => 'info',
            self::InDiagnosis => 'purple',
            self::InProgress  => 'warning',
            self::Testing     => 'indigo',
            self::Completed   => 'success',
            self::Returned    => 'cyan',
        };
    }

    /**
     * Statuses that still occupy a workshop unit slot.
     *
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return [self::Pending, self::Received, self::InDiagnosis, self::InProgress, self::Testing];
    }
}
