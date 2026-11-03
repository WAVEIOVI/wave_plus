<?php

namespace App\Modules\ClientAssets\Enums;

enum ComplianceStatus: string
{
    case COMPLIANT = 'compliant';
    case NON_COMPLIANT = 'non_compliant';
    case DUE_SOON = 'due_soon';
    case OVERDUE = 'overdue';
    case NOT_APPLICABLE = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::COMPLIANT => 'Compliant',
            self::NON_COMPLIANT => 'Non-Compliant',
            self::DUE_SOON => 'Due Soon',
            self::OVERDUE => 'Overdue',
            self::NOT_APPLICABLE => 'N/A',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
