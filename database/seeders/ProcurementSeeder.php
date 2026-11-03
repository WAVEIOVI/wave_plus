<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\IprLine;
use App\Modules\Procurement\Models\PoLine;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Database\Seeder;

class ProcurementSeeder extends Seeder
{
    /**
     * Run the database seeds for procurement demonstration and baseline testing.
     */
    public function run(): void
    {
        $user = User::first();
        $supplier = Supplier::first();
        $item = CatalogItem::first();
        $variant = CatalogVariant::first();

        if (! $user || ! $supplier || ! $item) {
            return;
        }

        // Avoid duplicate seeding on repeated runs
        if (InternalPurchaseRequest::where('requested_by', $user->id)->exists()) {
            return;
        }

        // 1. Seed sample Internal Purchase Request (IPR)
        $ipr = InternalPurchaseRequest::create([
            'trigger_source' => 'manual_user',
            'requested_by' => $user->id,
            'urgency' => 'normal',
            'status' => 'approved',
            'needed_by_date' => now()->addDays(14)->toDateString(),
            'internal_justification' => 'Replenishment for workshop fire safety inventory.',
            'approval_note' => 'Approved by procurement management.',
        ]);

        $iprLine = IprLine::create([
            'ipr_id' => $ipr->id,
            'catalog_item_id' => $item->id,
            'variant_id' => $variant?->id,
            'is_ad_hoc' => false,
            'qty_requested' => 10.00,
            'qty_current_stock' => 2.00,
            'qty_on_order' => 0.00,
            'suggested_supplier_id' => $supplier->id,
            'estimated_unit_cost' => 45.000,
        ]);

        // 2. Seed sample Purchase Order (PO)
        $po = PurchaseOrder::create([
            'creation_path' => 'from_ipr',
            'ipr_ids' => [$ipr->id],
            'supplier_id' => $supplier->id,
            'currency' => 'TND',
            'exchange_rate' => 1.0,
            'payment_terms' => 'net_30',
            'total_ht' => 450.000,
            'total_tax' => 85.500,
            'total_ttc' => 535.500,
            'approval_required' => false,
            'status' => 'sent',
            'committed_delivery_date' => now()->addDays(10)->toDateString(),
            'notes_to_supplier' => 'Standard warehouse delivery required.',
            'internal_notes' => 'Generated from seeded IPR.',
        ]);

        PoLine::create([
            'po_id' => $po->id,
            'catalog_item_id' => $item->id,
            'variant_id' => $variant?->id,
            'ipr_line_id' => $iprLine->id,
            'is_ad_hoc' => false,
            'qty_ordered' => 10.00,
            'unit_price_ht' => 45.000,
            'tax_rate_pct' => 19.00,
            'total_ht' => 450.000,
            'total_tax' => 85.500,
            'total_ttc' => 535.500,
            'qty_received' => 0.00,
            'fulfillment_status' => 'pending',
        ]);
    }
}
