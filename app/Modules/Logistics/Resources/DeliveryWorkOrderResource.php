<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use App\Modules\Logistics\Enums\DwoStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryWorkOrderResource extends JsonResource
{
    /**
     * Map each DWO status to a human-readable primary action key and label.
     * These represent the NEXT recommended business action (not just a status change).
     */
    private const NEXT_ACTION_MAP = [
        DwoStatus::Draft->value              => ['key' => 'plan_delivery',       'label' => 'Plan Delivery'],
        DwoStatus::Planned->value            => ['key' => 'start_preparation',   'label' => 'Start Preparation'],
        DwoStatus::ReadyToPick->value        => ['key' => 'start_picking',       'label' => 'Start Picking'],
        DwoStatus::Picking->value            => ['key' => 'complete_picking',    'label' => 'Complete Picking'],
        DwoStatus::ReadyToLoad->value        => ['key' => 'load_vehicle',        'label' => 'Load Vehicle'],
        DwoStatus::Loaded->value             => ['key' => 'dispatch',            'label' => 'Dispatch'],
        DwoStatus::Dispatched->value         => ['key' => 'start_delivery',      'label' => 'Start Delivery'],
        DwoStatus::InTransit->value          => ['key' => 'confirm_delivery',    'label' => 'Confirm Delivery'],
        DwoStatus::Delivered->value          => ['key' => 'close_delivery',      'label' => 'Close Delivery'],
        DwoStatus::PartiallyDelivered->value => ['key' => 'close_delivery',      'label' => 'Close Delivery'],
        DwoStatus::DeliveryFailed->value     => ['key' => 'reschedule',          'label' => 'Reschedule'],
        DwoStatus::Exception->value          => ['key' => 'resolve_exception',   'label' => 'Resolve Exception'],
        DwoStatus::Closed->value             => null,
        DwoStatus::Cancelled->value          => null,
    ];

    /**
     * Map DWO status to workflow phase (for progress indicator).
     */
    private const WORKFLOW_PHASES = [
        'planning'     => [DwoStatus::Draft->value, DwoStatus::Planned->value],
        'preparation'  => [DwoStatus::ReadyToPick->value, DwoStatus::Picking->value],
        'loading'      => [DwoStatus::ReadyToLoad->value, DwoStatus::Loaded->value],
        'dispatch'     => [DwoStatus::Dispatched->value],
        'delivery'     => [DwoStatus::InTransit->value],
        'completion'   => [DwoStatus::Delivered->value, DwoStatus::PartiallyDelivered->value, DwoStatus::Closed->value],
        'exception'    => [DwoStatus::Exception->value],
        'failed'       => [DwoStatus::DeliveryFailed->value, DwoStatus::Cancelled->value],
    ];

    private const PHASE_ORDER = ['planning', 'preparation', 'loading', 'dispatch', 'delivery', 'completion'];

    private function resolveWorkflowPhase(string $statusValue): string
    {
        foreach (self::WORKFLOW_PHASES as $phase => $statuses) {
            if (in_array($statusValue, $statuses, true)) {
                return $phase;
            }
        }
        return 'planning';
    }

    private function resolveWorkflowProgress(string $phase): int
    {
        return match ($phase) {
            'planning'    => 10,
            'preparation' => 30,
            'loading'     => 50,
            'dispatch'    => 65,
            'delivery'    => 80,
            'completion'  => 100,
            'exception'   => 70,
            'failed'      => 70,
            default       => 0,
        };
    }

    private function buildReadiness(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : collect();

        $totalLines       = $lines->count();
        $allocatedLines   = $lines->filter(fn ($l) => (float) $l->qty_allocated > 0)->count();
        $reservedLines    = $lines->filter(fn ($l) => (float) ($l->qty_reserved ?? 0) >= (float) $l->qty_allocated)->count();
        $pickedLines      = $lines->filter(fn ($l) => (float) $l->qty_picked >= (float) $l->qty_allocated)->count();
        $loadedLines      = $lines->filter(fn ($l) => (float) $l->qty_loaded >= (float) $l->qty_allocated)->count();

        // Allocation readiness: all lines have qty_allocated > 0
        $allocationReady  = $totalLines > 0 && $allocatedLines === $totalLines;
        // Reservation readiness: all allocated lines have full reservation
        $reservationReady = $totalLines > 0 && $reservedLines === $totalLines;
        // Picking readiness: all allocated lines fully picked
        $pickingReady     = $totalLines > 0 && $pickedLines === $totalLines;
        // Loading readiness: all picked lines fully loaded
        $loadingReady     = $totalLines > 0 && $loadedLines === $totalLines;

        $driverReady      = (bool) $this->driver_id || (bool) $this->approved_driver_id;
        $vehicleReady     = (bool) $this->vehicle_id;
        $destinationReady = (bool) ($this->delivery_bp_address_id || $this->delivery_address_snapshot);

        return [
            'allocation_ready'   => $allocationReady,
            'allocation_detail'  => [
                'allocated_lines' => $allocatedLines,
                'total_lines'     => $totalLines,
            ],
            'reservation_ready'  => $reservationReady,
            'reservation_detail' => [
                'reserved_lines' => $reservedLines,
                'total_lines'    => $totalLines,
            ],
            'picking_ready'      => $pickingReady,
            'picking_detail'     => [
                'picked_lines' => $pickedLines,
                'total_lines'  => $totalLines,
            ],
            'loading_ready'      => $loadingReady,
            'loading_detail'     => [
                'loaded_lines' => $loadedLines,
                'total_lines'  => $totalLines,
            ],
            'driver_ready'       => $driverReady,
            'vehicle_ready'      => $vehicleReady,
            'destination_ready'  => $destinationReady,
            'overall_ready'      => $allocationReady && $driverReady && $vehicleReady && $destinationReady,
            'blocking_issues'    => $this->buildBlockingIssues($allocationReady, $reservationReady, $pickingReady, $driverReady, $vehicleReady, $destinationReady),
        ];
    }

    private function buildBlockingIssues(
        bool $allocation, bool $reservation, bool $picking, 
        bool $driver, bool $vehicle, bool $destination
    ): array {
        $issues = [];
        if (! $allocation)   { $issues[] = 'Allocation incomplete'; }
        if (! $reservation)  { $issues[] = 'Stock reservation incomplete'; }
        if (! $picking)      { $issues[] = 'Picking incomplete'; }
        if (! $driver)       { $issues[] = 'No driver assigned'; }
        if (! $vehicle)      { $issues[] = 'No vehicle assigned'; }
        if (! $destination)  { $issues[] = 'No delivery address set'; }
        return $issues;
    }

    public function toArray(Request $request): array
    {
        $statusValue = $this->status instanceof DwoStatus
            ? $this->status->value
            : (string) $this->status;

        $allowed = [];
        if ($this->status instanceof DwoStatus) {
            foreach ($this->status->allowedTransitions() as $target) {
                $allowed[] = [
                    'value' => $target->value,
                    'label' => $target->label(),
                    'color' => $target->badgeColor(),
                ];
            }
        }

        $nextActionConfig = self::NEXT_ACTION_MAP[$statusValue] ?? null;
        if ($statusValue === DwoStatus::Dispatched->value && !$this->deliveryNote) {
            $nextActionConfig = ['key' => 'generate_delivery_note', 'label' => 'Generate Delivery Note'];
        }
        $workflowPhase    = $this->resolveWorkflowPhase($statusValue);
        $progress         = $this->resolveWorkflowProgress($workflowPhase);
        $readiness        = $this->buildReadiness();

        return [
            'id'                        => $this->id,
            'dwo_number'                => $this->dwo_number,
            // ── Workflow / UX helpers ────────────────────────────────────────
            'next_action'               => $nextActionConfig ? [
                'key'       => $nextActionConfig['key'],
                'label'     => $nextActionConfig['label'],
                // The target transition value (first forward transition) – may be null for terminal states
                'transition' => $allowed[0]['value'] ?? null,
            ] : null,
            'workflow'                  => [
                'phase'        => $workflowPhase,
                'current_step' => $statusValue,
                'progress'     => $progress,
                'phase_order'  => self::PHASE_ORDER,
            ],
            'readiness'                 => $readiness,
            // ────────────────────────────────────────────────────────────────
            'sales_order_id'            => $this->sales_order_id,
            'sales_order'               => $this->whenLoaded('salesOrder', fn () => [
                'id'                      => $this->salesOrder->id,
                'reference'               => $this->salesOrder->reference,
                'status'                  => $this->salesOrder->status?->value ?? (string) $this->salesOrder->status,
                'committed_delivery_date' => $this->salesOrder->committed_delivery_date?->format('Y-m-d'),
                'total_ttc'               => $this->salesOrder->total_ttc,
            ]),
            'client_id'                 => $this->client_id,
            'client'                    => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'legal_name'      => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
                'primary_phone'   => $this->client->primary_phone,
                'primary_email'   => $this->client->primary_email,
            ]),
            'warehouse_id'              => $this->warehouse_id,
            'warehouse'                 => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'delivery_bp_address_id'    => $this->delivery_bp_address_id,
            'delivery_address'          => $this->whenLoaded('deliveryAddress', fn () => [
                'id'             => $this->deliveryAddress->id,
                'label'          => $this->deliveryAddress->label,
                'address_street' => $this->deliveryAddress->address_street,
                'city'           => $this->deliveryAddress->city,
                'postcode'       => $this->deliveryAddress->postcode,
                'country'        => $this->deliveryAddress->country,
                'phone'          => $this->deliveryAddress->phone,
]),
            'delivery_site_id'          => $this->delivery_site_id,
            'delivery_site'             => $this->whenLoaded('deliverySite', fn () => [
                'id'   => $this->deliverySite->id,
                'name' => $this->deliverySite->name,
            ]),
            'delivery_address_snapshot' => $this->delivery_address_snapshot,
            'approved_driver_id'        => $this->approved_driver_id,
            'approved_driver'           => $this->whenLoaded('approvedDriver', fn () => [
                'id'           => $this->approvedDriver->id,
                'driver_authorization_number' => $this->approvedDriver->driver_authorization_number,
                'employee_name' => $this->approvedDriver->employee?->full_name ?? $this->approvedDriver->employee?->first_name ?? '—',
                'license_class' => $this->approvedDriver->license_category,
                'driver_score'  => $this->approvedDriver->driver_score,
                'is_active'     => $this->approvedDriver->is_active,
            ]),
            'mobile_warehouse_id'       => $this->mobile_warehouse_id,
            'mobile_warehouse'          => $this->whenLoaded('mobileWarehouse', fn () => [
                'id'   => $this->mobileWarehouse->id,
                'name' => $this->mobileWarehouse->name,
                'code' => $this->mobileWarehouse->code,
                'vehicle_id' => $this->mobileWarehouse->vehicle_id,
            ]),
            'vehicle_id'                => $this->vehicle_id,
            'vehicle'                   => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
                'type'         => $this->vehicle->type,
                'capacity_kg'  => (float) $this->vehicle->capacity_kg,
            ]),
            'wave_id'                   => $this->wave_id,
            'wave'                      => $this->whenLoaded('wave', fn () => [
                'id'          => $this->wave->id,
                'wave_number' => $this->wave->wave_number,
                'status'      => $this->wave->status?->value ?? (string) $this->wave->status,
            ]),
            'status'                    => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'grey',
            ],
            'priority'                  => [
                'value' => $this->priority?->value ?? (string) $this->priority,
                'label' => $this->priority?->label() ?? ucfirst((string) $this->priority),
                'color' => $this->priority?->badgeColor() ?? 'grey',
            ],
            'fulfillment_method'        => $this->fulfillment_method,
            'delivery_service_provider_id' => $this->delivery_service_provider_id,
            'delivery_service_provider'  => $this->whenLoaded('deliveryServiceProvider', fn () => [
                'id'          => $this->deliveryServiceProvider->id,
                'provider_code' => $this->deliveryServiceProvider->provider_code,
                'name'        => $this->deliveryServiceProvider->serviceProvider?->name ?? $this->deliveryServiceProvider->provider_code,
            ]),
            'carrier_name'              => $this->carrier_name,
            'estimated_delivery_cost'   => $this->estimated_delivery_cost !== null ? (float) $this->estimated_delivery_cost : null,
            'actual_delivery_cost'      => $this->actual_delivery_cost !== null ? (float) $this->actual_delivery_cost : null,
            'approved_driver_id'        => $this->approved_driver_id,
            'approved_driver'           => $this->whenLoaded('approvedDriver', fn () => [
                'id'           => $this->approvedDriver->id,
                'driver_authorization_number' => $this->approvedDriver->driver_authorization_number,
                'employee_name' => $this->approvedDriver->employee?->full_name ?? $this->approvedDriver->employee?->first_name ?? '—',
                'license_class' => $this->approvedDriver->license_category,
                'driver_score'  => $this->approvedDriver->driver_score,
                'is_active'     => $this->approvedDriver->is_active,
            ]),
            'mobile_warehouse_id'       => $this->mobile_warehouse_id,
            'mobile_warehouse'          => $this->whenLoaded('mobileWarehouse', fn () => [
                'id'   => $this->mobileWarehouse->id,
                'name' => $this->mobileWarehouse->name,
                'code' => $this->mobileWarehouse->code,
                'vehicle_id' => $this->mobileWarehouse->vehicle_id,
            ]),
            'scheduled_date'            => $this->scheduled_date?->format('Y-m-d'),
            'planned_date'              => $this->planned_date?->format('Y-m-d'),
            'dispatched_at'             => $this->dispatched_at?->toISOString(),
            'delivered_at'              => $this->delivered_at?->toISOString(),
            'closed_at'                 => $this->closed_at?->toISOString(),
            'exception_at'              => $this->exception_at?->toISOString(),
            'eta'                       => $this->eta?->toISOString(),
            'notes'                     => $this->notes,
            'is_picking_complete'       => $this->isPickingComplete(),
            'is_dispatchable'           => $this->isDispatchable(),
            'is_closable'               => $this->isClosable(),
            'available_transitions'     => $allowed,
            'lines'                     => DwoLineResource::collection($this->whenLoaded('lines')),
            'pod'                       => new ProofOfDeliveryResource($this->whenLoaded('pod')),
            'exceptions'                => DeliveryExceptionResource::collection($this->whenLoaded('exceptions')),
            'status_histories'          => DeliveryStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'delivery_note'             => $this->whenLoaded('deliveryNote', fn () => [
                'id'                => $this->deliveryNote->id,
                'dn_number'         => $this->deliveryNote->dn_number,
                'delivery_date'     => $this->deliveryNote->delivery_date?->format('Y-m-d'),
                'recipient_name'    => $this->deliveryNote->recipient_name,
                'recipient_title'   => $this->deliveryNote->recipient_title,
                'signature_at'      => $this->deliveryNote->signature_at?->toISOString(),
                'stock_posted_at'   => $this->deliveryNote->stock_posted_at?->toISOString(),
                'status'            => $this->deliveryNote->status,
                'condition_overall' => $this->deliveryNote->condition_overall,
                'pdf_path'          => $this->deliveryNote->pdf_path,
                'client_remarks'    => $this->deliveryNote->client_remarks,
            ]),
            'reservations'              => $this->whenLoaded('reservations', fn () => $this->reservations->map(fn ($r) => [
                'id'                => $r->id,
                'catalog_item_id'   => $r->catalog_item_id,
                'catalog_item_name' => $r->catalogItem?->name,
                'qty_reserved'      => (float) $r->qty_reserved,
                'status'            => $r->status,
                'reserved_at'       => $r->reserved_at?->toISOString() ?? $r->created_at?->toISOString(),
            ])),
            'created_at'                => $this->created_at?->toISOString(),
            'updated_at'                => $this->updated_at?->toISOString(),
        ];
    }
}
