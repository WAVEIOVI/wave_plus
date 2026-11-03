<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Jobs;

use App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem;
use App\Modules\BusinessPartners\Services\BusinessPartnerContextualEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBpBulkEmailItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public string $itemId) {}

    public function handle(BusinessPartnerContextualEmailService $service): void
    {
        $item = BpBulkEmailBatchItem::with(['batch.createdByUser', 'batch.senderProfile', 'batch.template'])->find($this->itemId);

        if (! $item || $item->status === BpBulkEmailBatchItem::STATUS_SENT || $item->status === BpBulkEmailBatchItem::STATUS_SKIPPED) {
            return;
        }

        $batch = $item->batch;
        $user = $batch?->createdByUser;

        if (! $batch || ! $user) {
            $item->status = BpBulkEmailBatchItem::STATUS_FAILED;
            $item->error_message = 'Batch or originating user no longer exists.';
            $item->processed_at = now();
            $item->save();

            return;
        }

        $item->status = BpBulkEmailBatchItem::STATUS_PROCESSING;
        $item->save();

        try {
            $message = $service->sendSingleBulkItem(
                batch: $batch,
                item: $item,
                user: $user
            );

            $item->status = BpBulkEmailBatchItem::STATUS_SENT;
            $item->email_message_id = $message->id;
            $item->processed_at = now();
            $item->error_message = null;
            $item->save();
        } catch (\Throwable $e) {
            Log::error("Failed to process bulk email item [{$item->id}] for partner [{$item->partner_id}]: {$e->getMessage()}", [
                'item_id' => $item->id,
                'batch_id' => $batch->id,
                'partner_id' => $item->partner_id,
                'trace' => $e->getTraceAsString(),
            ]);

            $item->status = BpBulkEmailBatchItem::STATUS_FAILED;
            $item->error_message = $e->getMessage();
            $item->processed_at = now();
            $item->save();
        } finally {
            $batch->refresh()->updateProgress();
        }
    }
}
