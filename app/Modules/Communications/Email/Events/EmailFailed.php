<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Events;

use App\Modules\Communications\Email\Models\EmailMessage;

class EmailFailed
{
    public function __construct(
        public readonly EmailMessage $message,
        public readonly string $reason,
    ) {}
}
