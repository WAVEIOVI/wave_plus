<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Controllers\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Resources\StockItemResource;
use App\Modules\Inventory\Services\InventoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use AuthorizesInventoryRequests;

    public function __construct(
        private readonly InventoryQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', StockItem::class);

        $query = StockItem::with([
            'catalogItem.category',
            'catalogItem.uom',
            'catalogVariant',
            'location.zone.site',
            'stockLot',
        ]);

        if ($search = $request->input('q')) {
            $query->where(function ($outer) use ($search) {
                $outer->whereHas('catalogItem', function ($q) use ($search) {
                    $q->where(function ($itemQuery) use ($search) {
                        $itemQuery->where('code', 'like', '%'.$search.'%');
                        $this->queryService->applyLocalizedNameSearch($itemQuery, 'name', $search);
                    });
                })->orWhereHas('catalogVariant', function ($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%");
                })->orWhereHas('location', function ($q) use ($search) {
                    $q->where('barcode', 'like', "%{$search}%");
                });
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($condition = $request->input('filter.condition')) {
            $query->where('condition', $condition);
        }

        if ($siteId = $request->input('filter.site_id')) {
            $query->whereHas('location.zone', function ($q) use ($siteId) {
                $q->where('site_id', $siteId);
            });
        }

        if ($request->boolean('filter.below_reorder')) {
            $query->whereHas('catalogVariant', function ($q) {
                $q->whereNotNull('reorder_point')
                    ->where('reorder_point', '>', 0);
            })->whereRaw('qty_available <= (SELECT reorder_point FROM catalog_variants WHERE catalog_variants.id = stock_items.catalog_variant_id)');
        }

        // ── Sorting ────────────────────────────────────────────────
        $sortBy = $request->input('sort_by', $request->input('sortBy', 'updated_at'));
        $orderBy = $request->input('sort_order', $request->input('orderBy', 'desc'));

        $allowedSorts = ['qty_on_hand', 'qty_available', 'qty_reserved', 'unit_cost', 'updated_at', 'created_at', 'status', 'condition'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $orderBy === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = $this->queryService->cappedPerPage($request);

        return StockItemResource::collection($query->paginate($perPage));
    }

    /**
     * Show a single stock item.
     */
    public function show(string $id): StockItemResource
    {
        $stockItem = StockItem::with([
            'catalogItem.category',
            'catalogItem.uom',
            'catalogItem.brand',
            'catalogVariant',
            'location.zone.site',
            'stockLot',
            'activeReservations',
        ])->findOrFail($id);

        $this->authorize('view', $stockItem);

        return new StockItemResource($stockItem);
    }

    /**
     * Dashboard KPIs for the Inventory module.
     *
     * Returns: total_stock_items, total_inventory_value, items_below_reorder,
     *          active_locations, items_by_condition, recent_movements, warehouse_distribution.
     */
    public function dashboardStats(): JsonResponse
    {
        $this->authorize('viewAny', StockItem::class);

        return response()->json([
            'data' => [
                'kpis' => $this->kpis(),
                'items_by_condition' => $this->itemsByCondition(),
                'warehouse_distribution' => $this->warehouseDistribution(),
                'recent_movements' => $this->recentMovements(),
                'low_stock_alerts' => $this->lowStockAlerts(),
            ],
        ]);
    }

    // ─── KPI Aggregations ────────────────────────────────────────

    private function kpis(): array
    {
        $activeItems = DB::table('stock_items')
            ->whereNull('deleted_at')
            ->where('status', 'active');

        $totalStockItems = (clone $activeItems)->count();

        $totalInventoryValue = (clone $activeItems)
            ->selectRaw('SUM(qty_on_hand * COALESCE(unit_cost, 0)) as total')
            ->value('total') ?? 0;

        $totalQtyOnHand = (clone $activeItems)->sum('qty_on_hand');
        $totalQtyReserved = (clone $activeItems)->sum('qty_reserved');
        $totalQtyAvailable = (clone $activeItems)->sum('qty_available');

        // Items below their catalog_variant reorder_point
        $belowReorder = DB::table('stock_items')
            ->join('catalog_variants', 'stock_items.catalog_variant_id', '=', 'catalog_variants.id')
            ->whereNull('stock_items.deleted_at')
            ->where('stock_items.status', 'active')
            ->whereNotNull('catalog_variants.reorder_point')
            ->where('catalog_variants.reorder_point', '>', 0)
            ->whereRaw('stock_items.qty_available <= catalog_variants.reorder_point')
            ->count();

        // Active warehouse locations
        $activeLocations = DB::table('warehouse_locations')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        // Active warehouse sites
        $activeSites = DB::table('warehouse_sites')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->count();

        return [
            'total_stock_items' => [
                'count' => $totalStockItems,
                'trend' => $this->calculateTrend('stock_items'),
            ],
            'total_inventory_value' => [
                'value' => round((float) $totalInventoryValue, 3),
                'currency' => 'EUR',
            ],
            'qty_summary' => [
                'on_hand' => round((float) $totalQtyOnHand, 2),
                'reserved' => round((float) $totalQtyReserved, 2),
                'available' => round((float) $totalQtyAvailable, 2),
            ],
            'below_reorder' => [
                'count' => $belowReorder,
            ],
            'active_locations' => [
                'count' => $activeLocations,
            ],
            'active_sites' => [
                'count' => $activeSites,
            ],
        ];
    }

    private function calculateTrend(string $table): array
    {
        $last30 = DB::table($table)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNull('deleted_at')
            ->count();

        $prev30 = DB::table($table)
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->whereNull('deleted_at')
            ->count();

        $growth = $prev30 > 0
            ? round((($last30 - $prev30) / $prev30) * 100, 1)
            : ($last30 > 0 ? 100 : 0);

        return [
            'growth' => $growth,
            'direction' => $growth >= 0 ? 'up' : 'down',
        ];
    }

    // ─── Items by Condition ──────────────────────────────────────

    private function itemsByCondition(): array
    {
        $rows = DB::table('stock_items')
            ->select(['condition', DB::raw('COUNT(*) as count')])
            ->whereNull('deleted_at')
            ->groupBy('condition')
            ->get();

        return [
            'labels' => $rows->pluck('condition')->map(fn ($c) => ucfirst($c))->toArray(),
            'series' => $rows->pluck('count')->map(fn ($c) => (int) $c)->toArray(),
        ];
    }

    // ─── Warehouse Distribution ──────────────────────────────────

    private function warehouseDistribution(): array
    {
        return DB::table('stock_items')
            ->join('warehouse_locations', 'stock_items.location_id', '=', 'warehouse_locations.id')
            ->join('warehouse_zones', 'warehouse_locations.zone_id', '=', 'warehouse_zones.id')
            ->join('warehouse_sites', 'warehouse_zones.site_id', '=', 'warehouse_sites.id')
            ->select([
                'warehouse_sites.id as site_id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(warehouse_sites.name, '$.en')) as site_name"),
                DB::raw('COUNT(stock_items.id) as items_count'),
                DB::raw('SUM(stock_items.qty_on_hand) as total_qty'),
                DB::raw('SUM(stock_items.qty_on_hand * COALESCE(stock_items.unit_cost, 0)) as total_value'),
            ])
            ->whereNull('stock_items.deleted_at')
            ->groupBy('warehouse_sites.id', 'site_name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get()
            ->toArray();
    }

    // ─── Recent Movements ────────────────────────────────────────

    private function recentMovements(): array
    {
        return DB::table('stock_movements')
            ->join('catalog_variants', 'stock_movements.catalog_variant_id', '=', 'catalog_variants.id')
            ->join('catalog_items', 'stock_movements.catalog_item_id', '=', 'catalog_items.id')
            ->select([
                'stock_movements.id',
                'stock_movements.reference',
                'stock_movements.movement_type',
                'stock_movements.quantity',
                'stock_movements.performed_at',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(catalog_items.name, '$.en')) as item_name"),
                'catalog_variants.sku as item_code',
            ])
            ->orderByDesc('stock_movements.performed_at')
            ->limit(8)
            ->get()
            ->toArray();
    }

    // ─── Low Stock Alerts ────────────────────────────────────────

    private function lowStockAlerts(): array
    {
        return DB::table('stock_items')
            ->join('catalog_variants', 'stock_items.catalog_variant_id', '=', 'catalog_variants.id')
            ->join('catalog_items', 'stock_items.catalog_item_id', '=', 'catalog_items.id')
            ->select([
                'stock_items.id',
                'stock_items.qty_available',
                'catalog_variants.reorder_point',
                'catalog_variants.sku as item_code',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(catalog_items.name, '$.en')) as item_name"),
            ])
            ->whereNull('stock_items.deleted_at')
            ->where('stock_items.status', 'active')
            ->whereNotNull('catalog_variants.reorder_point')
            ->where('catalog_variants.reorder_point', '>', 0)
            ->whereRaw('stock_items.qty_available <= catalog_variants.reorder_point')
            ->orderByRaw('stock_items.qty_available / catalog_variants.reorder_point ASC')
            ->limit(10)
            ->get()
            ->toArray();
    }
}
