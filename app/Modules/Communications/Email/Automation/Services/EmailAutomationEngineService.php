<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Services;

use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\Communications\Email\Automation\Enums\AutomationExecutionMode;
use App\Modules\Communications\Email\Automation\Enums\AutomationExecutionStatus;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationLog;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\Procurement\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailAutomationEngineService
{
    public function __construct(
        protected ConditionEvaluatorService $evaluator,
        protected ActionExecutorService $executor
    ) {}

    /**
     * Dispatch an automation trigger for a business record.
     *
     * @return array<int, EmailAutomationLog>
     */
    public function trigger(string $trigger, Model $record, array $context = []): array
    {
        // 1. Fetch active rules matching the trigger
        $rules = EmailAutomationRule::query()
            ->active()
            ->forTrigger($trigger)
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $logs = [];

        foreach ($rules as $rule) {
            $log = $this->processRuleForRecord($rule, $record, $context);
            if ($log) {
                $logs[] = $log;
            }
        }

        return $logs;
    }

    /**
     * Process a single rule against a record with idempotency and safety checks.
     */
    public function processRuleForRecord(EmailAutomationRule $rule, Model $record, array $context = []): ?EmailAutomationLog
    {
        // 1. Current record state validation (e.g. skip deleted or cancelled records)
        if (! $this->isRecordInValidStateForAutomation($record)) {
            return null;
        }

        // 2. Evaluate conditions safely
        if (! $this->evaluator->evaluate($rule->conditions, $record, $context)) {
            return null;
        }

        // 3. Compute unique idempotency key to prevent duplicate execution
        $idempotencyKey = $this->generateIdempotencyKey($rule, $record);

        // 4. Check if already executed or pending
        $existing = EmailAutomationLog::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && in_array($existing->status, [
            AutomationExecutionStatus::Executed->value,
            AutomationExecutionStatus::Approved->value,
            AutomationExecutionStatus::Pending->value,
        ], true)) {
            return $existing; // Duplicate execution prevented
        }

        // 5. Create or reuse log entry
        $log = $existing ?? EmailAutomationLog::create([
            'rule_id' => $rule->id,
            'trigger' => $rule->trigger,
            'record_type' => class_basename($record),
            'record_id' => (string) $record->getKey(),
            'execution_mode' => $rule->execution_mode,
            'status' => AutomationExecutionStatus::Pending->value,
            'idempotency_key' => $idempotencyKey,
            'attempts' => 0,
            'metadata' => [
                'record_reference' => $record->reference ?? $record->invoice_number ?? $record->id,
                'trigger_at' => now()->toIso8601String(),
            ],
        ]);

        // 6. Execute actions with error and retry handling
        return $this->executeLog($rule, $record, $log);
    }

    /**
     * Execute an automation log entry.
     */
    public function executeLog(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log): EmailAutomationLog
    {
        $log->increment('attempts');

        try {
            DB::beginTransaction();

            $res = $this->executor->execute($rule, $record, $log);

            $status = ($rule->execution_mode === AutomationExecutionMode::ApprovalRequired->value && $res['approval_request_id'])
                ? AutomationExecutionStatus::Pending->value
                : AutomationExecutionStatus::Executed->value;

            $log->update([
                'status' => $status,
                'email_message_id' => $res['email_message_id'],
                'approval_request_id' => $res['approval_request_id'],
                'follow_up_task_id' => $res['follow_up_task_id'],
                'executed_at' => now(),
                'error_message' => null,
            ]);

            DB::commit();

            return $log;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("Automation rule [{$rule->name}] failed for record [{$log->record_type}:{$log->record_id}]: ".$e->getMessage());

            $log->update([
                'status' => AutomationExecutionStatus::Failed->value,
                'error_message' => $e->getMessage(),
            ]);

            return $log;
        }
    }

    /**
     * Retry a failed automation execution log.
     */
    public function retry(int $logId): bool
    {
        $log = EmailAutomationLog::with('rule')->findOrFail($logId);
        if ($log->status !== AutomationExecutionStatus::Failed->value) {
            return false;
        }

        $record = $this->resolveRecordModel($log->record_type, $log->record_id);
        if (! $record) {
            $log->update(['error_message' => 'Record no longer exists.']);

            return false;
        }

        $this->executeLog($log->rule, $record, $log);

        return $log->status === AutomationExecutionStatus::Executed->value;
    }

    /**
     * Scan scheduled events across supported models.
     */
    public function scanScheduledTriggers(): array
    {
        $summary = [
            'invoice_overdue' => 0,
            'equipment_maintenance_due' => 0,
            'quotation_pending' => 0,
        ];

        // 1. Invoices Overdue
        $overdueInvoices = SalesInvoice::query()
            ->where('due_date', '<', Carbon::today())
            ->where('status', 'posted')
            ->where('payment_status', '!=', 'paid')
            ->limit(50)
            ->get();

        foreach ($overdueInvoices as $inv) {
            $logs = $this->trigger('invoice.overdue', $inv);
            $summary['invoice_overdue'] += count($logs);
        }

        // 2. Equipment Maintenance Due
        $maintenanceEquipment = ClientEquipment::query()
            ->whereNotNull('next_inspection_date')
            ->where('next_inspection_date', '<=', Carbon::today()->addDays(7))
            ->where('status', 'active')
            ->limit(50)
            ->get();

        foreach ($maintenanceEquipment as $eq) {
            $logs = $this->trigger('equipment.maintenance_due', $eq);
            $summary['equipment_maintenance_due'] += count($logs);
        }

        // 3. Quotations Pending
        $pendingQuotations = Quotation::query()
            ->whereIn('status', ['under_review', 'sent'])
            ->where('updated_at', '<=', Carbon::now()->subDays(2))
            ->limit(50)
            ->get();

        foreach ($pendingQuotations as $quo) {
            $logs = $this->trigger('quotation.pending', $quo);
            $summary['quotation_pending'] += count($logs);
        }

        return $summary;
    }

    /**
     * Verify that record is not deleted, voided, or cancelled.
     */
    protected function isRecordInValidStateForAutomation(Model $record): bool
    {
        // Ignore soft deleted records
        if (method_exists($record, 'trashed') && $record->trashed()) {
            return false;
        }

        $rawStatus = $record->status;
        if ($rawStatus instanceof \BackedEnum) {
            $rawStatus = $rawStatus->value;
        }
        $status = strtolower((string) ($rawStatus ?? ''));
        if (in_array($status, ['cancelled', 'voided', 'rejected', 'deleted', 'expired'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Generate an idempotency key preventing duplicate executions.
     */
    protected function generateIdempotencyKey(EmailAutomationRule $rule, Model $record): string
    {
        $base = "rule_{$rule->id}:".class_basename($record).":{$record->getKey()}:{$rule->trigger}";

        // Periodic events include date bucket (e.g. today or month) so reminders can fire on subsequent schedules
        if (in_array($rule->trigger, ['invoice.overdue', 'equipment.maintenance_due', 'quotation.pending'], true)) {
            return "{$base}:".Carbon::today()->toDateString();
        }

        return "{$base}:once";
    }

    protected function resolveRecordModel(string $type, string|int $id): ?Model
    {
        return match (strtolower($type)) {
            'quotation' => Quotation::find($id),
            'salesorder', 'sales_order' => \App\Modules\Crm\Models\SalesOrder::find($id),
            'purchaseorder', 'purchase_order' => PurchaseOrder::find($id),
            'salesinvoice', 'sales_invoice', 'invoice' => SalesInvoice::find($id),
            'mission' => Mission::find($id),
            'clientequipment', 'client_equipment', 'equipment' => ClientEquipment::find($id),
            default => null,
        };
    }
}
