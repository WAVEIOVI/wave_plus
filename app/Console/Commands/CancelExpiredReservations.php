<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancels stock reservations that have passed their expires_at timestamp.
 *
 * Scheduled to run hourly. Each expired reservation releases its
 * qty_reserved back to the parent stock_item via markCancelled().
 */
class CancelExpiredReservations extends Command
{
    protected $signature = 'inventory:cancel-expired-reservations';

    protected $description = 'Cancel stock reservations that have passed their expiry time and release held quantities.';

    public function handle(): int
    {
        $expired = StockReservation::expired()->with('stockItem')->get();

        if ($expired->isEmpty()) {
            $this->info('No expired reservations found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired reservation(s). Cancelling...");

        $cancelled = 0;
        $failed = 0;

        foreach ($expired as $reservation) {
            try {
                DB::transaction(function () use ($reservation) {
                    $reservation->markCancelled('Automatically cancelled — reservation expired.');
                });

                $cancelled++;

                Log::info('[Inventory] Expired reservation auto-cancelled', [
                    'reservation_id' => $reservation->id,
                    'stock_item_id' => $reservation->stock_item_id,
                    'qty_released' => $reservation->qty_reserved,
                ]);
            } catch (\Throwable $e) {
                $failed++;

                Log::error('[Inventory] Failed to cancel expired reservation', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to cancel reservation {$reservation->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Cancelled: {$cancelled}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
