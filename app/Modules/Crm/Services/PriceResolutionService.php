<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\PriceList;
use App\Modules\Crm\Models\PriceListLine;

class PriceResolutionService
{
    /**
     * Resolve the best applicable price for a catalog item.
     *
     * Resolution priority:
     * 1. Direct priceListId (e.g. from contract / caller)
     * 2. Contract-linked price list (if contractId provided)
     * 3. Client-specific active price list (if clientId provided)
     * 4. Active Promo / general active price list
     * 5. Catalog variant / item fallback
     *
     * @return array{price: float, source: string, price_list_id: string|null, currency: string}
     */
    public function resolvePrice(
        string $catalogItemId,
        ?string $variantId = null,
        ?string $clientId = null,
        ?string $contractId = null,
        ?string $priceListId = null
    ): array {
        // 1. Explicit price list
        if ($priceListId) {
            $line = $this->findPriceListLine($priceListId, $catalogItemId, $variantId);
            if ($line) {
                return [
                    'price' => (float) ($line->computed_price ?? $line->fixed_price ?? $line->sales_price ?? 0),
                    'source' => 'explicit_price_list',
                    'price_list_id' => $priceListId,
                    'currency' => (string) $line->priceList->currency,
                ];
            }
        }

        // 2. Contract price list
        if ($contractId) {
            $contract = Contract::find($contractId);
            if ($contract && $contract->price_list_id) {
                $line = $this->findPriceListLine($contract->price_list_id, $catalogItemId, $variantId);
                if ($line) {
                    return [
                        'price' => (float) ($line->computed_price ?? $line->fixed_price ?? $line->sales_price ?? 0),
                        'source' => 'contract_price_list',
                        'price_list_id' => $contract->price_list_id,
                        'currency' => $contract->currency ?? 'TND',
                    ];
                }
            }
        }

        // 3. Client-specific active price list
        if ($clientId) {
            $clientPriceList = PriceList::where('client_id', $clientId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('valid_to')->orWhere('valid_to', '>=', today());
                })
                ->latest()
                ->first();

            if ($clientPriceList) {
                $line = $this->findPriceListLine($clientPriceList->id, $catalogItemId, $variantId);
                if ($line) {
                    return [
                        'price' => (float) ($line->computed_price ?? $line->fixed_price ?? $line->sales_price ?? 0),
                        'source' => 'client_price_list',
                        'price_list_id' => $clientPriceList->id,
                        'currency' => $clientPriceList->currency ?? 'TND',
                    ];
                }
            }
        }

        // 4. Default active general / promo price list
        $defaultPriceList = PriceList::whereNull('client_id')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', today());
            })
            ->latest()
            ->first();

        if ($defaultPriceList) {
            $line = $this->findPriceListLine($defaultPriceList->id, $catalogItemId, $variantId);
            if ($line) {
                return [
                    'price' => (float) ($line->computed_price ?? $line->fixed_price ?? $line->sales_price ?? 0),
                    'source' => 'standard_price_list',
                    'price_list_id' => $defaultPriceList->id,
                    'currency' => $defaultPriceList->currency ?? 'TND',
                ];
            }
        }

        // 5. Fall back to variant price or catalog item sales_price / base_price
        if ($variantId) {
            $variant = CatalogVariant::find($variantId);
            if ($variant && (float) $variant->price > 0) {
                return [
                    'price' => (float) $variant->price,
                    'source' => 'variant_default',
                    'price_list_id' => null,
                    'currency' => 'TND',
                ];
            }
        }

        $catalogItem = CatalogItem::with(['variants' => fn ($q) => $q->where('is_default', true)])
            ->find($catalogItemId);

        $defaultVariant = $catalogItem?->variants->first();
        $fallbackPrice = $defaultVariant ? (float) $defaultVariant->price : 0.0;

        return [
            'price' => $fallbackPrice,
            'source' => $defaultVariant ? 'variant_default' : 'catalog_fallback',
            'price_list_id' => null,
            'currency' => 'TND',
        ];
    }

    private function findPriceListLine(string $priceListId, string $catalogItemId, ?string $variantId = null): ?PriceListLine
    {
        $query = PriceListLine::with('priceList')
            ->where('price_list_id', $priceListId)
            ->where('catalog_item_id', $catalogItemId);

        if ($variantId) {
            $variantLine = (clone $query)->where('variant_id', $variantId)->first();
            if ($variantLine) {
                return $variantLine;
            }
        }

        return $query->whereNull('variant_id')->first();
    }
}
