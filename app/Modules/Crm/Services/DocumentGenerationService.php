<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Enums\DocumentStatus;
use App\Modules\Crm\Models\GcsDocument;
use App\Modules\Crm\Models\LsaDocument;
use App\Modules\Crm\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class DocumentGenerationService
{
    /**
     * Generate an immutable GCS (Goods Commitment Sheet) snapshot document
     * for an accepted Sales Order of type 'sales'.
     *
     * IDEMPOTENT: Returns existing GCS if one already exists for this SO.
     */
    public function generateGcs(SalesOrder $so): GcsDocument
    {
        // 1. Check existing to ensure idempotency
        $existing = GcsDocument::where('sales_order_id', $so->id)->first();
        if ($existing) {
            if (! $so->gcs_id) {
                $so->forceFill(['gcs_id' => $existing->id])->save();
            }

            return $existing;
        }

        return DB::transaction(function () use ($so) {
            $so->loadMissing(['lines.catalogItem', 'lines.variant', 'lines.unit', 'contract', 'client', 'locations.bpAddress', 'locations.clientSite']);

            $goodsLinesSnapshot = [];
            foreach ($so->lines as $line) {
                $sku = $line->variant ? $line->variant->sku : ($line->catalogItem ? $line->catalogItem->sku : ($line->ad_hoc_sku ?? 'N/A'));
                /** @var mixed $rawName */
                $rawName = $line->catalogItem ? $line->catalogItem->name : null;
                if (is_array($rawName)) {
                    $catalogName = (string) ($rawName['en'] ?? (reset($rawName) ?: 'Product'));
                } elseif (is_string($rawName) && $rawName !== '') {
                    $catalogName = $rawName;
                } else {
                    $catalogName = 'Product';
                }
                $name = $line->is_ad_hoc ? (string) $line->ad_hoc_name : $catalogName;
                $unitCode = $line->unit ? $line->unit->code : 'unit';

                $goodsLinesSnapshot[] = [
                    'line_id' => $line->id,
                    'catalog_item_id' => $line->catalog_item_id,
                    'variant_id' => $line->variant_id,
                    'is_ad_hoc' => $line->is_ad_hoc,
                    'sku' => $sku,
                    'name' => $name,
                    'qty' => (float) $line->qty,
                    'unit' => $unitCode,
                    'unit_price' => (float) $line->unit_price,
                    'discount_pct' => (float) $line->discount_pct,
                    'total_ht' => (float) $line->total_ht,
                    'total_tva' => (float) $line->total_tva,
                    'total_ttc' => (float) $line->total_ttc,
                ];
            }

            $deliveryAddressSnapshot = null;
            $primaryLoc = $so->locations->firstWhere('is_primary', true) ?? $so->locations->first();
            if ($primaryLoc && $primaryLoc->bpAddress) {
                $bpAddr = $primaryLoc->bpAddress;
                $street = is_array($bpAddr->address_street) ? ($bpAddr->address_street['en'] ?? reset($bpAddr->address_street)) : ($bpAddr->address_street ?? $bpAddr->street ?? '');
                $city = is_array($bpAddr->city) ? ($bpAddr->city['en'] ?? reset($bpAddr->city)) : ($bpAddr->city ?? '');
                $siteName = $primaryLoc->clientSite ? ($primaryLoc->clientSite->name ?? 'Site') : 'Site';
                if (is_array($siteName)) {
                    $siteName = $siteName['en'] ?? reset($siteName);
                }

                $deliveryAddressSnapshot = [
                    'site_name' => (string) $siteName,
                    'address_line_1' => (string) $street,
                    'city' => (string) $city,
                    'country' => (string) ($bpAddr->country ?? 'Tunisia'),
                ];
            } elseif ($so->client) {
                $deliveryAddressSnapshot = [
                    'client_name' => $so->client->legal_name ?? $so->client->commercial_name,
                    'country' => 'Tunisia',
                ];
            }

            $committedDate = $so->committed_delivery_date_from
                ?? $so->requested_delivery_date_from
                ?? now()->addDays(7);

            $gcs = GcsDocument::create([
                'sales_order_id' => $so->id,
                'contract_id' => $so->contract_id,
                'status' => DocumentStatus::Generated,
                'committed_delivery_date' => $committedDate,
                'goods_lines' => $goodsLinesSnapshot,
                'delivery_address_snapshot' => $deliveryAddressSnapshot,
                'total_ht' => (float) $so->total_ht,
                'total_tva' => (float) $so->total_tva,
                'total_ttc' => (float) $so->total_ttc,
                'payment_terms' => $so->payment_terms,
                'incoterms' => 'DAP',
                'special_delivery_instructions' => $so->client_notes,
            ]);

            $so->forceFill(['gcs_id' => $gcs->id])->save();

            return $gcs;
        });
    }

    /**
     * Generate an immutable LSA (Lettre de Service Après-vente) snapshot document
     * for an accepted Sales Order of type 'service'.
     *
     * IDEMPOTENT: Returns existing LSA if one already exists for this SO.
     */
    public function generateLsa(SalesOrder $so): LsaDocument
    {
        // 1. Check existing to ensure idempotency
        $existing = LsaDocument::where('sales_order_id', $so->id)->first();
        if ($existing) {
            if (! $so->lsa_id) {
                $so->forceFill(['lsa_id' => $existing->id])->save();
            }

            return $existing;
        }

        return DB::transaction(function () use ($so) {
            $so->loadMissing(['lines.catalogItem', 'contract', 'client']);

            $contract = $so->contract;

            // Snapshot SLA and quality commitments from linked contract or system defaults
            $responseHours = $contract && $contract->standard_response_time_hours ? (int) $contract->standard_response_time_hours : ($contract && $contract->sla_response_hours ? (int) $contract->sla_response_hours : 4);
            $resolutionHours = $contract && $contract->sla_resolution_hours ? (int) $contract->sla_resolution_hours : 24;
            $qualityStandard = $contract && $contract->service_quality_standard ? (string) $contract->service_quality_standard : 'Standard ISO Service & Maintenance SLA';

            $serviceScopeSnapshot = [];
            foreach ($so->lines as $line) {
                /** @var mixed $rawServiceName */
                $rawServiceName = $line->catalogItem ? $line->catalogItem->name : null;
                if (is_array($rawServiceName)) {
                    $catalogServiceName = (string) ($rawServiceName['en'] ?? (reset($rawServiceName) ?: 'Service'));
                } elseif (is_string($rawServiceName) && $rawServiceName !== '') {
                    $catalogServiceName = $rawServiceName;
                } else {
                    $catalogServiceName = 'Service';
                }
                $serviceName = $line->is_ad_hoc ? (string) $line->ad_hoc_name : $catalogServiceName;

                $serviceScopeSnapshot[] = [
                    'line_id' => $line->id,
                    'catalog_item_id' => $line->catalog_item_id,
                    'service_name' => $serviceName,
                    'qty' => (float) $line->qty,
                    'unit_price' => (float) $line->unit_price,
                    'total_ht' => (float) $line->total_ht,
                    'delivery_date' => $line->delivery_date?->toDateString(),
                ];
            }

            $startDate = $so->committed_delivery_date_from ?? $so->requested_delivery_date_from ?? now()->addDays(1);
            $endDate = $so->committed_delivery_date_to ?? $so->requested_delivery_date_to ?? (clone $startDate)->addDays(30);

            $equipmentTypes = ($contract && is_array($contract->covered_equipment_types)) ? $contract->covered_equipment_types : [];

            $lsa = LsaDocument::create([
                'sales_order_id' => $so->id,
                'contract_id' => $so->contract_id,
                'status' => DocumentStatus::Generated,
                'sla_response_hours' => $responseHours,
                'sla_resolution_hours' => $resolutionHours,
                'scheduled_start_date' => $startDate,
                'scheduled_end_date' => $endDate,
                'service_scope' => $serviceScopeSnapshot,
                'equipment_scope' => $equipmentTypes,
                'special_terms' => $qualityStandard,
            ]);

            $so->forceFill(['lsa_id' => $lsa->id])->save();

            return $lsa;
        });
    }
}
