<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Events\SalesOrderAccepted;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Handles the commercial approval of a Sales Order.
 *
 * RESPONSIBILITY:
 *   - Validate feasibility gate
 *   - Mark SO as commercially accepted
 *   - Generate the appropriate commitment document (GCS or LSA)
 *   - Fire the domain event
 *
 * OUT OF SCOPE — belongs to downstream operational modules:
 *   - Stock reservation (owned by DWO allocation)
 *   - Stock movements (owned by Delivery Note confirmation)
 *   - Creating Delivery Work Orders (DWO)
 *   - Creating Service Work Orders (SWO)
 *   - Moving SO to an operational/dispatched status
 *
 * Downstream modules create DWO/SWO from the approved SO independently,
 * allowing multiple operational records per Sales Order (fan-out model).
 */
class SalesOrderAcceptanceService
{
    public function __construct(
        private DocumentGenerationService $documentGenerationService,
    ) {}

    /**
     * Accept a Sales Order commercially: validate feasibility, generate the
     * appropriate commitment document, and fire the event.
     *
     * IDEMPOTENT: safely retryable without duplicating GCS or LSA.
     * The SO status remains 'accepted'. No inventory reservation or stock
     * movement is created at acceptance — those start at DWO / Delivery Note.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function accept(SalesOrder $so, string $actorId): SalesOrder
    {
        return DB::transaction(function () use ($so, $actorId) {
            $so->loadMissing(['lines.catalogItem', 'client']);

            // 1. Enforce feasibility review gate
            if ($so->feasibility_status !== FeasibilityStatus::Approved) {
                abort(422, "Cannot accept Sales Order {$so->reference}: Feasibility review is not approved (current: {$so->feasibility_status->value}).");
            }

            // 2. Mark SO as commercially accepted
            $so->update([
                'status' => SalesOrderStatus::Accepted,
                'confirmed_at' => $so->confirmed_at ?? now(),
                'confirmed_by' => $so->confirmed_by ?? $actorId,
            ]);

            if ($so->isSales()) {
                // GCS = commercial confirmation. Does NOT reserve stock or trigger delivery.
                $this->documentGenerationService->generateGcs($so);
            } elseif ($so->isService()) {
                // LSA = service commitment. Does NOT create SWO or assign technicians.
                $this->documentGenerationService->generateLsa($so);
            }

            event(new SalesOrderAccepted($so, $actorId));

            return $so->fresh(['lines.catalogItem', 'gcsDocument', 'lsaDocument']);
        });
    }
}
