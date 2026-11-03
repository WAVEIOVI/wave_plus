<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogOption;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Requests\StoreCatalogOptionRequest;
use App\Modules\Catalog\Requests\StoreCatalogVariantRequest;
use App\Modules\Catalog\Requests\UpdateCatalogOptionRequest;
use App\Modules\Catalog\Requests\UpdateCatalogVariantRequest;
use App\Modules\Catalog\Resources\CatalogOptionResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogVariantController extends Controller
{
    use AuthorizesCatalogRequests;

    public function indexOptions(string $catalogItemId): JsonResponse
    {
        $item = CatalogItem::findOrFail($catalogItemId);
        $this->authorize('view', $item);

        $options = CatalogOption::with('values')
            ->where('catalog_item_id', $catalogItemId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => CatalogOptionResource::collection($options),
        ]);
    }

    public function storeOption(StoreCatalogOptionRequest $request): JsonResponse
    {
        $item = CatalogItem::findOrFail($request->validated('catalog_item_id'));
        $this->authorize('update', $item);

        $option = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $values = $validated['values'] ?? [];
            unset($validated['values']);

            $option = CatalogOption::create($validated);

            foreach ($values as $valueData) {
                $option->values()->create($valueData);
            }

            return $option;
        });

        return response()->json([
            'message' => 'Option created successfully.',
            'data' => new CatalogOptionResource($option->load('values')),
        ], 201);
    }

    public function updateOption(UpdateCatalogOptionRequest $request, string $option): JsonResponse
    {
        $record = CatalogOption::findOrFail($option);
        $this->authorize('update', $record->catalogItem);

        DB::transaction(function () use ($record, $request) {
            $validated = $request->validated();
            $values = $validated['values'] ?? null;
            unset($validated['values']);

            $record->update($validated);

            if ($values !== null) {
                $existingIds = [];
                foreach ($values as $index => $valData) {
                    $valData['sort_order'] = $valData['sort_order'] ?? $index;
                    if (! empty($valData['id'])) {
                        $val = $record->values()->find($valData['id']);
                        if ($val) {
                            $val->update($valData);
                            $existingIds[] = $val->id;
                            continue;
                        }
                    }
                    $newVal = $record->values()->create($valData);
                    $existingIds[] = $newVal->id;
                }
                $record->values()->whereNotIn('id', $existingIds)->delete();
            }
        });

        return response()->json([
            'message' => 'Option updated successfully.',
            'data' => new CatalogOptionResource($record->fresh()->load('values')),
        ]);
    }

    public function destroyOption(string $option): JsonResponse
    {
        $record = CatalogOption::findOrFail($option);
        $this->authorize('update', $record->catalogItem);
        $record->delete();

        return response()->json(['message' => 'Option deleted successfully.']);
    }

    public function indexVariants(string $catalogItemId): JsonResponse
    {
        $item = CatalogItem::findOrFail($catalogItemId);
        $this->authorize('view', $item);

        $variants = CatalogVariant::with(['optionValues', 'stockItems'])
            ->where('catalog_item_id', $catalogItemId)
            ->get();

        return response()->json([
            'data' => CatalogVariantResource::collection($variants),
        ]);
    }

    public function storeVariant(StoreCatalogVariantRequest $request): JsonResponse
    {
        $item = CatalogItem::findOrFail($request->validated('catalog_item_id'));
        $this->authorize('update', $item);

        $variant = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $optionValueIds = $validated['option_value_ids'] ?? [];
            unset($validated['option_value_ids'], $validated['stock']);

            $variant = CatalogVariant::create($validated);

            if (! empty($optionValueIds)) {
                $variant->optionValues()->sync($optionValueIds);
            }

            return $variant;
        });

        return response()->json([
            'message' => 'Variant created successfully.',
            'data' => new CatalogVariantResource($variant->load('optionValues')),
        ], 201);
    }

    public function updateVariant(UpdateCatalogVariantRequest $request, string $variant): JsonResponse
    {
        $record = CatalogVariant::findOrFail($variant);
        $this->authorize('update', $record->catalogItem);

        DB::transaction(function () use ($record, $request) {
            $validated = $request->validated();
            $optionValueIds = $validated['option_value_ids'] ?? null;
            unset($validated['option_value_ids'], $validated['stock']);

            $record->update($validated);

            if ($optionValueIds !== null) {
                $record->optionValues()->sync($optionValueIds);
            }
        });

        return response()->json([
            'message' => 'Variant updated successfully.',
            'data' => new CatalogVariantResource($record->fresh()->load('optionValues')),
        ]);
    }

    public function destroyVariant(string $variant): JsonResponse
    {
        $record = CatalogVariant::findOrFail($variant);
        $this->authorize('update', $record->catalogItem);

        try {
            $record->delete();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['variant' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json(['message' => 'Variant deleted successfully.']);
    }
}
