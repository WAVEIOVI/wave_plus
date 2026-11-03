<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogDashboardController extends Controller
{
    use AuthorizesCatalogRequests;

    public function dashboardStats(): JsonResponse
    {
        $this->authorize('viewAny', CatalogItem::class);

        $kpis = $this->kpis();

        return response()->json([
            'data' => [
                'kpis' => $kpis,
                'items_by_type' => $this->itemsByType(),
                'blueprints_by_category' => $this->blueprintsByCategory(),
                'recent_activity' => $this->recentActivity(),
            ],
        ]);
    }

    // ─── KPI Cards ──────────────────────────────────────────────

    private function kpis(): array
    {
        return [
            'catalog_items' => $this->getKpiWithTrend('catalog_items'),
            'blueprints' => $this->getKpiWithTrend('equipment_blueprints'),
            'guidelines' => $this->getKpiWithTrend('guidelines', false),
            'categories' => [
                'count' => DB::table('catalog_categories')->count(),
                'growth' => 0,
                'trend' => 'up',
            ],
        ];
    }

    private function getKpiWithTrend(string $tableName, bool $hasSoftDeletes = true): array
    {
        $query = DB::table($tableName);
        if ($hasSoftDeletes) {
            $query->whereNull('deleted_at');
        }
        $totalCount = $query->count();

        $query30 = DB::table($tableName)->where('created_at', '>=', now()->subDays(30));
        if ($hasSoftDeletes) {
            $query30->whereNull('deleted_at');
        }
        $last30Count = $query30->count();

        $queryPrev30 = DB::table($tableName)
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30));
        if ($hasSoftDeletes) {
            $queryPrev30->whereNull('deleted_at');
        }
        $prev30Count = $queryPrev30->count();

        $growth = 0;
        if ($prev30Count > 0) {
            $growth = (($last30Count - $prev30Count) / $prev30Count) * 100;
        } elseif ($last30Count > 0) {
            $growth = 100;
        }

        return [
            'count' => $totalCount,
            'growth' => round($growth, 1),
            'trend' => $growth >= 0 ? 'up' : 'down',
        ];
    }

    // ─── Items by Type (Donut Chart) ────────────────────────────

    private function itemsByType(): array
    {
        $rows = DB::table('catalog_items')
            ->select(['item_type', DB::raw('COUNT(*) as count')])
            ->whereNull('deleted_at')
            ->groupBy('item_type')
            ->get();

        $labels = [];
        $series = [];

        foreach ($rows as $row) {
            $labels[] = match ($row->item_type) {
                'product' => 'Products',
                'consumable' => 'Consumables',
                'eq_servicing' => 'Servicing',
                default => ucfirst($row->item_type),
            };
            $series[] = (int) $row->count;
        }

        return compact('labels', 'series');
    }

    // ─── Blueprints by Equipment Class (Bar Chart) ──────────────

    private function blueprintsByCategory(): array
    {
        $rows = DB::table('equipment_blueprints')
            ->leftJoin('equipment_classes', 'equipment_blueprints.equipment_class_id', '=', 'equipment_classes.id')
            ->select([
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(equipment_classes.name, '$.en')), equipment_classes.code, 'No Class') as class_name"),
                DB::raw('COUNT(equipment_blueprints.id) as count'),
            ])
            ->whereNull('equipment_blueprints.deleted_at')
            ->groupBy('class_name')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $labels = [];
        $series = [];

        foreach ($rows as $row) {
            $labels[] = $row->class_name;
            $series[] = (int) $row->count;
        }

        return compact('labels', 'series');
    }

    // ─── Recent Activity (Union of Items + Blueprints) ──────────

    private function recentActivity(): array
    {
        $itemQuery = DB::table('catalog_items')
            ->select([
                'id',
                DB::raw("'catalog_item' as source"),
                'item_type as sub_type',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) as name"),
                'code',
                'status',
                'updated_at',
            ])
            ->whereNull('deleted_at');

        $blueprintQuery = DB::table('equipment_blueprints')
            ->leftJoin('equipment_classes', 'equipment_blueprints.equipment_class_id', '=', 'equipment_classes.id')
            ->select([
                'equipment_blueprints.id',
                DB::raw("'blueprint' as source"),
                DB::raw("COALESCE(equipment_classes.code, 'blueprint') as sub_type"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(equipment_blueprints.name, '$.en')) as name"),
                'equipment_blueprints.code',
                'equipment_blueprints.status',
                'equipment_blueprints.updated_at',
            ])
            ->whereNull('equipment_blueprints.deleted_at');

        return $itemQuery
            ->union($blueprintQuery)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->toArray();
    }
}
