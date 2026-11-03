<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Enums;

enum AutomationActionType: string
{
    case SendEmail = 'send_email';
    case PrepareDraft = 'prepare_draft';
    case CreateApprovalRequest = 'create_approval_request';
    case CreateFollowUpTask = 'create_follow_up_task';
    case SendInternalNotification = 'send_internal_notification';
}
