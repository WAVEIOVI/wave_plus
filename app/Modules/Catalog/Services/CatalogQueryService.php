<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogQueryService
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public const LEGACY_UNBOUNDED_CAP = 200;

    public function applySearch($query, Request $request, array $columns)
    {
        $search = trim((string) $request->input('q', ''));

        if ($search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    public function paginate($query, Request $request, string $resourceClass): JsonResponse
    {
        $requested = $request->input('per_page', $request->input('itemsPerPage', self::DEFAULT_PER_PAGE));
        $unbounded = (int) $requested === -1;
        $perPage = $unbounded
            ? self::LEGACY_UNBOUNDED_CAP
            : max(1, min((int) $requested, self::MAX_PER_PAGE));

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $resourceClass::collection($paginated->items()),
            'total' => $paginated->total(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * Lookup lists (categories, brands, UoM). Cap unbounded fetches.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    public function lookup($query, Request $request, string $resourceClass): JsonResponse
    {
        $requested = $request->input('per_page', $request->input('itemsPerPage', self::LEGACY_UNBOUNDED_CAP));
        $limit = (int) $requested === -1
            ? self::LEGACY_UNBOUNDED_CAP
            : max(1, min((int) $requested, self::LEGACY_UNBOUNDED_CAP));

        $rows = $query->limit($limit)->get();

        return response()->json([
            'data' => $resourceClass::collection($rows),
            'total' => $rows->count(),
        ]);
    }

    public function itemOptions($query, Request $request): JsonResponse
    {
        $this->applySearch($query, $request, ['code', 'name']);

        if ($type = $request->input('item_type')) {
            $query->where('item_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'active');
        }

        $limit = max(1, min((int) $request->input('limit', 50), 100));

        $rows = $query
            ->with(['uom:id,code,name', 'variants' => fn ($q) => $q->where('is_default', true)->select('id', 'catalog_item_id', 'sku', 'price', 'is_default')])
            ->select(['id', 'code', 'name', 'item_type', 'status', 'uom_id', 'category_id'])
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $default = $item->variants->first();

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'item_type' => $item->item_type,
                    'status' => $item->status,
                    'uom_id' => $item->uom_id,
                    'uom_code' => $item->uom?->code,
                    'default_variant_id' => $default?->id,
                    'sku' => $default?->sku,
                    'price' => $default?->price,
                ];
            });

        return response()->json([
            'data' => $rows,
            'total' => $rows->count(),
        ]);
    }

    public function equipmentClassListQuery(Request $request)
    {
        $query = \App\Modules\Catalog\Models\EquipmentClass::with('childrenRecursive')
            ->when($request->boolean('roots_only'), fn ($q) => $q->whereNull('parent_id'))
            ->withCount(['blueprints', 'equipment']);

        $this->applySearch($query, $request, ['code', 'name']);

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $sortBy = in_array($request->input('sortBy'), ['sort_order', 'code', 'created_at', 'status'], true)
            ? $request->input('sortBy')
            : 'sort_order';
        $orderBy = $request->input('orderBy') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $orderBy);
    }
}
