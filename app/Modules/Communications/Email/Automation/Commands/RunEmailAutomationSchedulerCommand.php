<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Commands;

use App\Modules\Communications\Email\Automation\Services\EmailAutomationEngineService;
use Illuminate\Console\Command;

class RunEmailAutomationSchedulerCommand extends Command
{
    protected $signature = 'email:run-automation';

    protected $description = 'Scan and trigger scheduled email automations (overdue invoices, maintenance due, pending quotes)';

    public function handle(EmailAutomationEngineService $engine): int
    {
        $this->info('Starting email automation scheduled scan...');

        $summary = $engine->scanScheduledTriggers();

        $this->info('Completed scan:');
        $this->line("- Overdue Invoices: {$summary['invoice_overdue']}");
        $this->line("- Equipment Maintenance Due: {$summary['equipment_maintenance_due']}");
        $this->line("- Quotations Pending: {$summary['quotation_pending']}");

        return Command::SUCCESS;
    }
}
