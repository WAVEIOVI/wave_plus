<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Catalog\Services\CatalogLineSnapshotService;
use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Enums\SoFulfillmentMode;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canonical Quotation / Contract → Sales Order conversion.
 *
 * Mixed quotations are split into separate Goods (sales) and Service SOs.
 * Never creates a mixed Goods+Service Sales Order.
 */
class SalesOrderConversionService
{
    public function __construct(
        private readonly CatalogLineSnapshotService $lineSnapshotService,
    ) {}

    /**
     * Create Sales Order(s) from an accepted Quotation.
     *
     * Pure goods or pure service → one SO.
     * Mixed → two SOs (goods + service), both traced to the quotation.
     *
     * @return Collection<int, SalesOrder>
     */
    public function createFromQuotation(Quotation $quote): Collection
    {
        if ($quote->status !== QuotationStatus::Accepted) {
            abort(422, "Cannot convert quotation {$quote->reference} to Sales Order: Status must be 'accepted' (current: {$quote->status->value}).");
        }

        if ($quote->converted_to_so_id) {
            abort(422, "Quotation {$quote->reference} has already been converted to a sales order.");
        }

        return DB::transaction(function () use ($quote) {
            $quote->loadMissing(['lines.catalogItem', 'contract', 'client']);

            [$goodsLines, $serviceLines] = $this->partitionQuotationLines($quote->lines);

            $created = collect();

            if ($goodsLines->isNotEmpty()) {
                $created->push($this->buildSoFromQuotationLines(
                    $quote,
                    SalesOrderType::Sales,
                    $goodsLines,
                ));
            }

            if ($serviceLines->isNotEmpty()) {
                $created->push($this->buildSoFromQuotationLines(
                    $quote,
                    SalesOrderType::Service,
                    $serviceLines,
                ));
            }

            if ($created->isEmpty()) {
                abort(422, "Quotation {$quote->reference} has no convertible lines.");
            }

            // Traceability: primary pointer = first created SO; both keep quotation_id.
            $quote->update([
                'converted_to_so_id' => $created->first()->id,
                'status' => QuotationStatus::Converted,
            ]);

            return $created->map(fn (SalesOrder $so) => $so->load(['client', 'lines', 'quotation', 'contract', 'locations']));
        });
    }

    /**
     * Back-compat: return the primary (first) SO from conversion.
     * Prefer createFromQuotation() which returns the full collection.
     */
    public function createSingleFromQuotation(Quotation $quote): SalesOrder
    {
        return $this->createFromQuotation($quote)->first();
    }

    /**
     * Create a Sales Order (call-off) from an active MSA/Contract.
     * Does not invent fake quotation lines.
     */
    public function createFromContract(Contract $contract): SalesOrder
    {
        if ($contract->status !== ContractStatus::Active) {
            abort(422, "Cannot create call-off from contract {$contract->reference}: Contract must be 'active' (current: {$contract->status->value}).");
        }

        return DB::transaction(function () use ($contract) {
            $contract->loadMissing(['lines.catalogItem', 'client', 'priceList']);

            $so = SalesOrder::create([
                'so_type' => SalesOrderType::Service,
                'creation_path' => SoCreationPath::ContractCallOff,
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'price_list_id' => $contract->price_list_id,
                'status' => SalesOrderStatus::Draft,
                'feasibility_status' => FeasibilityStatus::Pending,
                'currency' => $contract->currency ?? 'TND',
                'payment_terms' => $contract->payment_terms,
                'pricing_locked_at' => now(),
                'requested_fulfillment_mode' => SoFulfillmentMode::FieldService,
                'starter_locked_at' => now(),
                // Legacy compatibility flags — derived from so_type, not line inference for routing.
                'has_service_lines' => true,
                'has_goods_lines' => false,
            ]);

            $totalHt = $totalTva = $totalTtc = 0.0;

            if ($contract->lines->isNotEmpty()) {
                foreach ($contract->lines as $line) {
                    $qty = (float) ($line->contracted_qty ?? $line->agreed_quantity ?? 1);
                    $unitPrice = (float) ($line->contracted_unit_price ?? $line->agreed_unit_price ?? 0);
                    $discountPct = (float) ($line->discount_pct ?? 0);
                    $lineHt = $qty * $unitPrice * (1 - ($discountPct / 100));
                    $tvaRate = 19.0;
                    $lineTva = $lineHt * ($tvaRate / 100);
                    $lineTtc = $lineHt + $lineTva;

                    $lineData = [
                        'catalog_item_id' => $line->catalog_item_id,
                        'variant_id' => $line->variant_id,
                        'item_type_snapshot' => ItemTypeSnapshot::Service,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'discount_pct' => $discountPct,
                        'total_ht' => $lineHt,
                        'total_tva' => $lineTva,
                        'total_ttc' => $lineTtc,
                    ];
                    $so->lines()->create(array_merge($lineData, $this->lineSnapshotService->forLine($lineData)));

                    $totalHt += $lineHt;
                    $totalTva += $lineTva;
                    $totalTtc += $lineTtc;
                }

                $so->update([
                    'total_ht' => $totalHt,
                    'total_tva' => $totalTva,
                    'total_ttc' => $totalTtc,
                ]);
            }

            return $so->load(['client', 'lines', 'contract']);
        });
    }

    /**
     * @param  Collection<int, mixed>  $lines
     * @return array{0: Collection, 1: Collection}
     */
    private function partitionQuotationLines(Collection $lines): array
    {
        $goods = collect();
        $service = collect();

        foreach ($lines as $line) {
            $type = $line->item_type_snapshot instanceof ItemTypeSnapshot
                ? $line->item_type_snapshot->value
                : (string) $line->item_type_snapshot;

            if (in_array($type, ['service', 'eq_servicing'], true)) {
                $service->push($line);
            } else {
                $goods->push($line);
            }
        }

        return [$goods, $service];
    }

    /**
     * @param  Collection<int, mixed>  $lines
     */
    private function buildSoFromQuotationLines(
        Quotation $quote,
        SalesOrderType $soType,
        Collection $lines,
    ): SalesOrder {
        $totalHt = (float) $lines->sum('total_ht');
        $totalTva = (float) $lines->sum('total_tva');
        $totalTtc = (float) $lines->sum('total_ttc');

        $so = SalesOrder::create([
            'so_type' => $soType,
            'creation_path' => SoCreationPath::Quotation,
            'quotation_id' => $quote->id,
            'contract_id' => $quote->contract_id,
            'client_id' => $quote->client_id,
            'status' => SalesOrderStatus::Draft,
            'feasibility_status' => FeasibilityStatus::Pending,
            'currency' => $quote->currency ?? 'TND',
            'payment_terms' => $quote->payment_terms,
            'pricing_locked_at' => now(),
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
            // Compatibility mirrors of so_type — not used for routing.
            'has_service_lines' => $soType === SalesOrderType::Service,
            'has_goods_lines' => $soType === SalesOrderType::Sales,
            'client_notes' => $quote->cover_letter,
            'requested_fulfillment_mode' => $soType === SalesOrderType::Service
                ? SoFulfillmentMode::FieldService
                : SoFulfillmentMode::OwnDelivery,
            'starter_locked_at' => now(),
        ]);

        if ($quote->site_id) {
            $clientSite = DB::table('client_sites')->where('bp_address_id', $quote->site_id)->first();
            $so->locations()->create([
                'bp_address_id' => $quote->site_id,
                'client_site_id' => $clientSite?->id,
                'purpose' => $soType === SalesOrderType::Service ? 'service' : 'delivery',
                'is_primary' => true,
            ]);
        }

        foreach ($lines as $line) {
            $lineData = [
                'catalog_item_id' => $line->catalog_item_id,
                'variant_id' => $line->variant_id,
                'is_ad_hoc' => $line->is_ad_hoc,
                'ad_hoc_name' => $line->ad_hoc_name,
                'ad_hoc_sku' => $line->ad_hoc_sku,
                'item_type_snapshot' => $this->normalizeItemTypeSnapshot($line->item_type_snapshot),
                'qty' => $line->qty,
                'unit_price' => $line->unit_price,
                'discount_pct' => $line->discount_pct,
                'total_ht' => $line->total_ht,
                'total_tva' => $line->total_tva,
                'total_ttc' => $line->total_ttc,
                'description_snapshot' => $line->description_snapshot,
                'sku_snapshot' => $line->sku_snapshot,
                'uom_code_snapshot' => $line->uom_code_snapshot,
                'source_type' => $line->source_type ?? 'catalog',
                'source_blueprint_id' => $line->source_blueprint_id ?? null,
                'source_blueprint_option_values' => $line->source_blueprint_option_values ?? null,
                'source_servicing_id' => $line->source_servicing_id ?? null,
                'source_consumable_id' => $line->source_consumable_id ?? null,
                'calculation_snapshot' => $line->calculation_snapshot ?? null,
            ];
            $so->lines()->create(array_merge($lineData, $this->lineSnapshotService->forLine($lineData)));
        }

        return $so;
    }

    /**
     * Normalize item_type_snapshot to match SalesOrderLine enum values.
     * Maps legacy 'product' -> 'physical_product', 'eq_servicing' -> 'service', etc.
     */
    private function normalizeItemTypeSnapshot(mixed $type): string
    {
        $val = $type instanceof \BackedEnum ? $type->value : (string) $type;

        return match ($val) {
            'service', 'eq_servicing' => ItemTypeSnapshot::Service->value,
            'spare_part', 'consumable' => ItemTypeSnapshot::SparePart->value,
            'physical_product', 'product' => ItemTypeSnapshot::PhysicalProduct->value,
            default => ItemTypeSnapshot::PhysicalProduct->value,
        };
    }
}
