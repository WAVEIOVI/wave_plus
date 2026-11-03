<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryQueryService
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public const LEGACY_UNBOUNDED_CAP = 200;

    public function cappedPerPage(Request $request, int $default = self::DEFAULT_PER_PAGE): int
    {
        $requested = $request->input('per_page', $request->input('itemsPerPage', $default));

        if ((int) $requested === -1) {
            return self::LEGACY_UNBOUNDED_CAP;
        }

        return max(1, min((int) $requested, self::MAX_PER_PAGE));
    }

    public function applyLocalizedNameSearch(Builder $query, string $column, string $search): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $query->orWhere($column, 'like', '%'.$search.'%');

            return;
        }

        $query->orWhere(function ($q) use ($column, $search) {
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.en')) LIKE ?", ['%'.$search.'%'])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.fr')) LIKE ?", ['%'.$search.'%']);
        });
    }
}
