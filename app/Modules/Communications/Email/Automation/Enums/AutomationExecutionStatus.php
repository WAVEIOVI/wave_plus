<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Enums;

enum AutomationExecutionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executed = 'executed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
