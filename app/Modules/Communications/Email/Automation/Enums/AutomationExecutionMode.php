<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Enums;

enum AutomationExecutionMode: string
{
    case Automatic = 'automatic';
    case ApprovalRequired = 'approval_required';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic Execution',
            self::ApprovalRequired => 'Approval Required',
            self::Manual => 'Manual / Draft Only',
        };
    }
}
