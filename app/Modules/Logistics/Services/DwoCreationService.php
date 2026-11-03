<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Logistics\Enums\DwoLineStatus;
use App\Modules\Logistics\Enums\DwoPriority;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryStatusHistory;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DwoCreationService
{
    public function __construct(
        private readonly DwoReservationService $reservationService,
    ) {}

    /**
     * Inspect a Sales Order and calculate allocatable quantities per line.
     *
     * remaining = SO ordered - already delivered - currently open DWO allocation
     * Does NOT count completed/closed historical DWO allocation as still reserved.
     */
    public function getEligibleSalesOrderLines(SalesOrder $so): array
    {
        $so->loadMissing(['lines.catalogItem', 'lines.variant', 'lines.unit']);

        $results = [];

        foreach ($so->lines as $line) {
            $snapshotVal = $line->item_type_snapshot instanceof \App\Modules\Crm\Enums\ItemTypeSnapshot
                ? $line->item_type_snapshot->value
                : (string) ($line->item_type_snapshot ?? '');

            if (in_array($snapshotVal, ['service', 'eq_servicing'], true)) {
                continue;
            }

            // Quantities already DELIVERED (from closed/partially_delivered/delivered DWOs)
            $alreadyDelivered = (float) DwoLine::where('so_item_id', $line->id)
                ->whereHas('deliveryWorkOrder', function ($q) {
                    $q->whereIn('status', [
                        DwoStatus::Delivered->value,
                        DwoStatus::PartiallyDelivered->value,
                    ]);
                })
                ->sum('qty_delivered');

            // Quantities currently allocated in OPEN DWOs (not yet delivered)
            $currentlyAllocated = (float) DwoLine::where('so_item_id', $line->id)
                ->whereHas('deliveryWorkOrder', function ($q) {
                    $q->whereNotIn('status', [
                        DwoStatus::Cancelled->value,
                        DwoStatus::Closed->value,
                        DwoStatus::Delivered->value,
                        DwoStatus::PartiallyDelivered->value,
                        DwoStatus::DeliveryFailed->value,
                    ]);
                })
                ->sum('qty_allocated');

            $lineQty = (float) $line->qty;
            // remaining = ordered - delivered - open allocation
            $remaining = max(0.0, round($lineQty - $alreadyDelivered - $currentlyAllocated, 3));

            $results[] = [
                'so_line_id' => $line->id,
                'catalog_item_id' => $line->catalog_item_id,
                'catalog_variant_id' => $line->variant_id,
                'description' => $line->description_snapshot ?? $line->catalogItem?->name ?? 'Goods line',
                'variant_label' => $line->variant?->name ?? $line->variant?->sku,
                'uom_code' => $line->unit?->code ?? $line->catalogItem?->uom?->code ?? 'EA',
                'qty_ordered' => $lineQty,
                'qty_already_delivered' => $alreadyDelivered,
                'qty_currently_allocated' => $currentlyAllocated,
                'qty_remaining' => $remaining,
                'is_fully_allocated' => $remaining <= 0.0001,
            ];
        }

        return $results;
    }

    /**
     * Create a Delivery Work Order from an Accepted Goods Sales Order.
     * Allocation is independent of SO reservation (SO does not reserve stock).
     * Reservation is attempted for the allocated quantity only; shortage is allowed.
     */
    public function createFromSalesOrder(SalesOrder $so, array $data, ?User $actor = null): DeliveryWorkOrder
    {
        if ($so->status !== SalesOrderStatus::Accepted && (string) $so->status->value !== 'accepted') {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    "Delivery Work Orders can only be created for Accepted Sales Orders. Current status is: {$so->status->value}",
                ],
            ]);
        }

        // Gate on explicit so_type — NOT has_goods_lines.
        if ($so->so_type !== SalesOrderType::Sales) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'Delivery Work Orders can only be created from Goods (sales) Sales Orders. Service SOs route to Operational Hub.',
                ],
            ]);
        }

        $deliveryBpAddressId = ! empty($data['delivery_bp_address_id'])
            ? $data['delivery_bp_address_id']
            : $this->defaultDeliveryAddressId($so);
        $addressSnapshot = null;

        if ($deliveryBpAddressId) {
            $address = BpAddress::find($deliveryBpAddressId);
            if (! $address) {
                throw ValidationException::withMessages([
                    'delivery_bp_address_id' => ['The specified delivery address was not found.'],
                ]);
            }

            $addressSnapshot = [
                'id' => $address->id,
                'label' => $address->label,
                'address_street' => $address->address_street,
                'address_street2' => $address->address_street2,
                'city' => $address->city,
                'state' => $address->state,
                'postcode' => $address->postcode,
                'country' => $address->country,
                'phone' => $address->phone,
            ];
        }

        $deliverySiteId = ! empty($data['delivery_site_id'])
            ? (string) $data['delivery_site_id']
            : $this->defaultDeliverySiteId($so);

        if ($deliverySiteId) {
            $site = ClientSite::find($deliverySiteId);
            if (! $site || $site->client_id !== $so->client_id) {
                throw ValidationException::withMessages([
                    'delivery_site_id' => ['The delivery site does not belong to the sales order client.'],
                ]);
            }
        }

        return DB::transaction(function () use ($so, $data, $actor, $deliveryBpAddressId, $addressSnapshot, $deliverySiteId) {
            $requestedLines = $data['lines'] ?? [];
            if (empty($requestedLines)) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one line item must be allocated to create a Delivery Work Order.'],
                ]);
            }

            $preparedLines = [];

            foreach ($requestedLines as $index => $reqLine) {
                $soLineId = $reqLine['so_line_id'] ?? null;
                $reqQty = (float) ($reqLine['qty_to_deliver'] ?? $reqLine['qty'] ?? 0);

                if ($reqQty <= 0) {
                    continue;
                }

                /** @var SalesOrderLine|null $soLine */
                $soLine = SalesOrderLine::where('id', $soLineId)
                    ->where('sales_order_id', $so->id)
                    ->lockForUpdate()
                    ->with(['catalogItem.uom', 'variant', 'unit'])
                    ->first();

                if (! $soLine) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.so_line_id" => ["Sales Order Line {$soLineId} does not exist on this Sales Order."],
                    ]);
                }

                // Quantities already DELIVERED (from closed/partially_delivered/delivered DWOs)
                $alreadyDelivered = (float) DwoLine::where('so_item_id', $soLine->id)
                    ->whereHas('deliveryWorkOrder', function ($q) {
                        $q->whereIn('status', [
                            DwoStatus::Delivered->value,
                            DwoStatus::PartiallyDelivered->value,
                        ]);
                    })
                    ->lockForUpdate()
                    ->sum('qty_delivered');

                // Quantities currently allocated in OPEN DWOs (not yet delivered)
                $currentlyAllocated = (float) DwoLine::where('so_item_id', $soLine->id)
                    ->whereHas('deliveryWorkOrder', function ($q) {
                        $q->whereNotIn('status', [
                            DwoStatus::Cancelled->value,
                            DwoStatus::Closed->value,
                            DwoStatus::Delivered->value,
                            DwoStatus::PartiallyDelivered->value,
                            DwoStatus::DeliveryFailed->value,
                        ]);
                    })
                    ->lockForUpdate()
                    ->sum('qty_allocated');

                // remaining = ordered - delivered - open allocation
                $remainingAllocatable = max(0.0, round(((float) $soLine->qty) - $alreadyDelivered - $currentlyAllocated, 3));

                if ($reqQty > ($remainingAllocatable + 0.0001)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.qty_to_deliver" => [
                            "Requested quantity ({$reqQty}) exceeds remaining allocatable quantity ({$remainingAllocatable}) for item: "
                            .($soLine->catalogItem?->name ?? $soLine->description_snapshot ?? $soLine->id),
                        ],
                    ]);
                }

                $preparedLines[] = [
                    'so_line' => $soLine,
                    'qty_allocated' => $reqQty,
                    'location_id' => $reqLine['warehouse_location_id'] ?? null,
                    // Optional substitution proposal (requires later sales approval)
                    'proposed_catalog_item_id' => $reqLine['proposed_catalog_item_id'] ?? null,
                    'proposed_catalog_variant_id' => $reqLine['proposed_catalog_variant_id'] ?? null,
                    'substitution_reason' => $reqLine['substitution_reason'] ?? null,
                ];
            }

            if (empty($preparedLines)) {
                throw ValidationException::withMessages([
                    'lines' => ['No lines with quantity greater than zero were provided.'],
                ]);
            }

            $scheduledDate = $data['scheduled_date']
                ?? $so->committed_delivery_date_from?->toDateString()
                ?? $so->committed_delivery_date?->toDateString()
                ?? now()->toDateString();
            $plannedDate = $data['planned_date'] ?? $scheduledDate;
            $priority = isset($data['priority'])
                ? DwoPriority::tryFrom($data['priority']) ?? DwoPriority::Normal
                : DwoPriority::Normal;

            // Operational fulfillment may differ from SO commercial preference.
            $fulfillmentMethod = $data['fulfillment_method']
                ?? $this->mapSoFulfillmentToDwo($so->requested_fulfillment_mode?->value);

            $dwo = new DeliveryWorkOrder;
            $dwo->fill([
                'sales_order_id' => $so->id,
                'client_id' => $so->client_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'delivery_site_id' => $deliverySiteId,
                'delivery_bp_address_id' => $deliveryBpAddressId,
                'delivery_address_snapshot' => $addressSnapshot,
                'approved_driver_id' => $data['approved_driver_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'mobile_warehouse_id' => $data['mobile_warehouse_id'] ?? null,
                'scheduled_date' => $scheduledDate,
                'planned_date' => $plannedDate,
                'status' => DwoStatus::Draft,
                'priority' => $priority,
                'fulfillment_method' => $fulfillmentMethod,
                'delivery_service_provider_id' => $data['delivery_service_provider_id'] ?? null,
                'carrier_name' => $data['carrier_name'] ?? null,
                'estimated_delivery_cost' => $data['estimated_delivery_cost'] ?? null,
                'actual_delivery_cost' => $data['actual_delivery_cost'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
            $dwo->save();

            foreach ($preparedLines as $item) {
                /** @var SalesOrderLine $soLine */
                $soLine = $item['so_line'];
                $qty = $item['qty_allocated'];

                $hasSubstitution = ! empty($item['proposed_catalog_item_id'])
                    || ! empty($item['proposed_catalog_variant_id']);

                // When substitution is proposed, the ACTUAL operational item remains the commercial item.
                // The proposed item is stored separately and only becomes actual after approval.
                $actualItemId = $soLine->catalog_item_id;
                $actualVariantId = $soLine->variant_id;

                DwoLine::create([
                    'dwo_id' => $dwo->id,
                    'so_item_id' => $soLine->id,
                    'catalog_item_id' => $actualItemId,
                    'catalog_variant_id' => $actualVariantId,
                    'commercial_catalog_item_id' => $soLine->catalog_item_id,
                    'commercial_catalog_variant_id' => $soLine->variant_id,
                    'proposed_catalog_item_id' => $hasSubstitution ? ($item['proposed_catalog_item_id'] ?? null) : null,
                    'proposed_catalog_variant_id' => $hasSubstitution ? ($item['proposed_catalog_variant_id'] ?? null) : null,
                    'substitution_status' => $hasSubstitution ? 'proposed' : null,
                    'substitution_reason' => $hasSubstitution ? ($item['substitution_reason'] ?? null) : null,
                    'description_snapshot' => $soLine->description_snapshot ?? $soLine->catalogItem?->name ?? 'Goods Item',
                    'variant_label_snapshot' => $soLine->variant?->name ?? $soLine->variant?->sku,
                    'uom_code_snapshot' => $soLine->unit?->code ?? $soLine->catalogItem?->uom?->code ?? 'EA',
                    'qty_ordered' => (float) $soLine->qty,
                    'qty_allocated' => $qty,
                    'qty_reserved' => 0,
                    'qty_shortage' => 0,
                    'qty_picked' => 0,
                    'qty_loaded' => 0,
                    'qty_delivered' => 0,
                    'qty_returned' => 0,
                    'warehouse_location_id' => $item['location_id'],
                    'item_status' => DwoLineStatus::Pending,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);
            }

            // Allocation → attempt reservation for allocated qty only (shortage allowed).
            // Do NOT transfer SO reservations — SO no longer owns reservations.
            $dwo->load('lines.catalogItem');
            if ($actor) {
                $this->reservationService->reserveForDwo($dwo, $actor->id);
            }

            DeliveryStatusHistory::create([
                'dwo_id' => $dwo->id,
                'from_status' => null,
                'to_status' => DwoStatus::Draft,
                'changed_by' => $actor?->id,
                'changed_at' => now(),
                'notes' => 'Created Delivery Work Order from Sales Order '.($so->reference ?? $so->id),
            ]);

            return $dwo->load(['lines.catalogItem', 'lines.catalogVariant', 'lines.salesOrderLine', 'deliveryAddress', 'client']);
        });
    }

    private function defaultDeliveryAddressId(SalesOrder $so): ?int
    {
        $so->loadMissing('locations');
        $primary = $so->locations->firstWhere('is_primary', true) ?? $so->locations->first();

        return $primary?->bp_address_id ? $primary->bp_address_id : null;
    }

    private function defaultDeliverySiteId(SalesOrder $so): ?string
    {
        $so->loadMissing('locations');
        $primary = $so->locations->firstWhere('is_primary', true) ?? $so->locations->first();

        return $primary?->client_site_id ? (string) $primary->client_site_id : null;
    }

    private function mapSoFulfillmentToDwo(?string $soMode): ?string
    {
        return match ($soMode) {
            'own_delivery' => 'own_fleet',
            'third_party_delivery' => 'third_party',
            'customer_pickup' => 'customer_pickup',
            default => $soMode,
        };
    }
}
