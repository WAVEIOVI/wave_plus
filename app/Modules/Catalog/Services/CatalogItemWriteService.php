<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogItemWriteService
{
    public function create(array $validated, Request $request): CatalogItem
    {
        return DB::transaction(function () use ($validated, $request) {
            $extensionKey = $validated['item_type'] === 'eq_servicing' ? 'eq_servicing' : $validated['item_type'];
            $extensionData = $validated[$extensionKey] ?? [];

            $baseData = collect($validated)
                ->except([
                    'product', 'consumable', 'eq_servicing',
                    'variants', 'sku', 'base_price', 'cost_price', 'markup',
                    'tax_class', 'min_stock_level', 'reorder_point', 'reorder_qty', 'lead_time_days',
                    'suppliers',
                ])
                ->toArray();

            $baseData['created_by'] = $request->user()?->id;

            $item = CatalogItem::query()->create($baseData);

            $this->syncExtension($item, (string) $validated['item_type'], $extensionData);
            $this->syncSuppliers($item, $validated['suppliers'] ?? null);
            $this->syncVariants($item, $validated['variants'] ?? [], true);

            return $item->fresh([
                'category', 'brand', 'uom', 'product', 'consumable', 'eqServicing', 'suppliers', 'media', 'variants',
            ]);
        });
    }

    public function update(CatalogItem $item, array $validated, Request $request): CatalogItem
    {
        return DB::transaction(function () use ($item, $validated, $request) {
            $baseData = collect($validated)
                ->except([
                    'product', 'consumable', 'eq_servicing',
                    'variants', 'sku', 'base_price', 'cost_price', 'markup',
                    'tax_class', 'min_stock_level', 'reorder_point', 'reorder_qty', 'lead_time_days',
                    'suppliers',
                ])
                ->toArray();

            $baseData['updated_by'] = $request->user()?->id;
            $item->update($baseData);

            $itemType = (string) ($validated['item_type'] ?? $item->item_type);
            $extensionKey = $itemType === 'eq_servicing' ? 'eq_servicing' : $itemType;

            if (isset($validated[$extensionKey])) {
                $this->syncExtension($item, $itemType, $validated[$extensionKey]);
            }

            if (array_key_exists('suppliers', $validated)) {
                $this->syncSuppliers($item, $validated['suppliers']);
            }

            if (isset($validated['variants'])) {
                $this->syncVariants($item, $validated['variants'], false);
            }

            return $item->fresh([
                'category', 'brand', 'uom', 'product', 'consumable', 'eqServicing', 'suppliers', 'media', 'variants',
            ]);
        });
    }

    public function generateVariants(CatalogItem $item, array $variants): int
    {
        $created = 0;

        DB::transaction(function () use ($item, $variants, &$created) {
            foreach ($variants as $vData) {
                if ($item->variants()->where('sku', $vData['sku'])->exists()) {
                    continue;
                }

                $variant = $item->variants()->create([
                    'sku' => $vData['sku'],
                    'barcode' => $vData['barcode'] ?? null,
                    'price' => $vData['price'] ?? 0,
                    'cost_price' => $vData['cost_price'] ?? 0,
                    'status' => 'active',
                ]);

                if (! empty($vData['option_value_ids'])) {
                    $variant->optionValues()->sync($vData['option_value_ids']);
                }

                $created++;
            }
        });

        return $created;
    }

    public function delete(CatalogItem $item): void
    {
        try {
            $item->delete();
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;
            if ($sqlState === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                abort(409, 'This catalog item cannot be deleted while related documents still exist.');
            }

            throw $e;
        }
    }

    private function syncExtension(CatalogItem $item, string $itemType, array $extensionData): void
    {
        if ($extensionData === []) {
            return;
        }

        $consumables = $extensionData['consumables'] ?? null;
        unset($extensionData['consumables']);

        $extRecord = match ($itemType) {
            'product' => $item->product()->updateOrCreate(
                ['catalog_item_id' => $item->id],
                $extensionData
            ),
            'consumable' => $item->consumable()->updateOrCreate(
                ['catalog_item_id' => $item->id],
                $extensionData
            ),
            'eq_servicing' => $item->eqServicing()->updateOrCreate(
                ['catalog_item_id' => $item->id],
                $extensionData
            ),
            default => null,
        };

        if ($itemType === 'eq_servicing' && $extRecord && is_array($consumables)) {
            $extRecord->consumables()->sync(collect($consumables)
                ->mapWithKeys(fn ($c) => [$c['catalog_item_id'] => [
                    'quantity' => $c['quantity'],
                    'unit' => $c['unit'] ?? null,
                ]])
                ->toArray());
        }
    }

    private function syncSuppliers(CatalogItem $item, ?array $suppliers): void
    {
        if ($suppliers === null) {
            return;
        }

        $syncData = [];
        foreach ($suppliers as $supplier) {
            $syncData[$supplier['supplier_id']] = [
                'supplier_reference' => $supplier['supplier_reference'] ?? null,
                'supplier_price' => $supplier['supplier_price'] ?? null,
                'currency' => $supplier['currency'] ?? 'TND',
                'lead_time_days' => $supplier['lead_time_days'] ?? null,
                'min_order_qty' => $supplier['min_order_qty'] ?? null,
                'is_preferred' => $supplier['is_preferred'] ?? false,
            ];
        }

        $item->suppliers()->sync($syncData);

        $remainingIds = $item->suppliers()->pluck('suppliers.id');
        if ($item->preferred_supplier_id && ! $remainingIds->contains($item->preferred_supplier_id)) {
            $item->update(['preferred_supplier_id' => null]);
        }
    }

    private function syncVariants(CatalogItem $item, array $variants, bool $creating): void
    {
        if (empty($variants)) {
            if ($creating || $item->variants()->doesntExist()) {
                $item->variants()->create([
                    'is_default' => true,
                    'name' => 'Default',
                    'sku' => $item->code . '-DEF',
                    'price' => $item->base_price ?? 0,
                    'cost_price' => $item->cost_price ?? 0,
                    'status' => 'active',
                ]);
            }
            return;
        }

        $keptIds = [];
        $hasDefault = false;

        // Determine if any variant is marked as default
        foreach ($variants as $v) {
            if (! empty($v['is_default'])) {
                $hasDefault = true;
                break;
            }
        }

        foreach ($variants as $index => $vData) {
            $isDefault = $hasDefault
                ? (bool) ($vData['is_default'] ?? false)
                : ($index === 0);

            $payload = [
                'name' => $vData['name'] ?? null,
                'sku' => $vData['sku'],
                'barcode' => $vData['barcode'] ?? null,
                'price' => $vData['price'] ?? 0,
                'cost_price' => $vData['cost_price'] ?? 0,
                'markup' => $vData['markup'] ?? null,
                'tax_class' => $vData['tax_class'] ?? 'standard_19pct',
                'min_stock_level' => $vData['min_stock_level'] ?? null,
                'reorder_point' => $vData['reorder_point'] ?? null,
                'reorder_qty' => $vData['reorder_qty'] ?? null,
                'lead_time_days' => $vData['lead_time_days'] ?? null,
                'status' => $vData['status'] ?? 'active',
                'is_default' => $isDefault,
            ];

            if (! empty($vData['id'])) {
                $variant = $item->variants()->find($vData['id']);
                if ($variant) {
                    $variant->update($payload);
                    $keptIds[] = $variant->id;
                    continue;
                }
            }

            $variant = $item->variants()->create($payload);
            $keptIds[] = $variant->id;
        }

        if (! $creating && $keptIds !== []) {
            $item->variants()->whereNotIn('id', $keptIds)->update(['status' => 'inactive']);
        }

        if ($item->variants()->where('is_default', true)->doesntExist()) {
            $item->variants()->orderBy('created_at')->first()?->update(['is_default' => true]);
        }
    }
}
