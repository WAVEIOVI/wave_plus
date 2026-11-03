<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Traits\GeneratesPdf;

class CatalogItemPdfController extends Controller
{
    use AuthorizesCatalogRequests, GeneratesPdf;

    public function exportList()
    {
        $this->authorize('viewAny', CatalogItem::class);

        $catalogItems = CatalogItem::with(['category', 'brand', 'uom'])
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $html = view('pdf.catalog-items.catalog-items-list', compact('catalogItems'))->render();

        $pdf = $this->makeBrowsershot($html, 'landscape')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="catalog-items-list.pdf"',
        ]);
    }

    public function exportSingle(string $id)
    {
        $catalogItem = CatalogItem::with([
            'category',
            'brand',
            'uom',
            'product',
            'consumable',
            'eqServicing',
            'suppliers',
            'media',
            'options.values',
            'variants.optionValues',
        ])->findOrFail($id);

        $this->authorize('view', $catalogItem);

        $html = view('pdf.catalog-items.catalog-item-record', compact('catalogItem'))->render();

        $pdf = $this->makeBrowsershot($html, 'portrait')->pdf();

        $filename = 'catalog-item-'.$catalogItem->code.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
