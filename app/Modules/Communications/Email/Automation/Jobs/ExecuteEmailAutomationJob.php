<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Jobs;

use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Communications\Email\Automation\Services\EmailAutomationEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteEmailAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $ruleId,
        public string $recordClass,
        public string|int $recordId,
        public array $context = []
    ) {}

    public function handle(EmailAutomationEngineService $engine): void
    {
        $rule = EmailAutomationRule::find($this->ruleId);
        if (! $rule || ! $rule->is_active) {
            return;
        }

        $record = ($this->recordClass)::find($this->recordId);
        if (! $record) {
            return;
        }

        $engine->processRuleForRecord($rule, $record, $this->context);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ExecuteEmailAutomationJob failed for Rule [{$this->ruleId}]: ".$exception->getMessage());
    }
}
