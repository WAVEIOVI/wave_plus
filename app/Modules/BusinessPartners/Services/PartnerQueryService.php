<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class PartnerQueryService
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public const LEGACY_UNBOUNDED_CAP = 200;

    public const MAX_EXPORT_BATCH = 500;

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Spatie\QueryBuilder\QueryBuilder  $query
     * @param  array<int, string>  $columns
     */
    public function applySearch($query, Request $request, array $columns)
    {
        $search = trim((string) $request->input('q', ''));

        if ($search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $column) {
                if (in_array($column, ['legal_name', 'commercial_name'], true)) {
                    foreach (['en', 'fr', 'ar'] as $lang) {
                        $q->orWhere("{$column}->{$lang}", 'like', '%'.$search.'%');
                    }
                } else {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            }
        });
    }

    /**
     * Paginate a partner list. Legacy `itemsPerPage=-1` is capped, not unlimited.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Spatie\QueryBuilder\QueryBuilder  $query
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
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $searchColumns
     * @param  array<int, string>  $select
     */
    public function options($query, Request $request, array $searchColumns, array $select, string $codeColumn): JsonResponse
    {
        $this->applySearch($query, $request, $searchColumns);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $limit = max(1, min((int) $request->input('limit', 50), 100));

        $rows = $query
            ->select($select)
            ->orderBy('legal_name->en')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($codeColumn) {
                return [
                    'id' => $row->id,
                    'code' => $row->{$codeColumn},
                    'legal_name' => $row->legal_name,
                    'commercial_name' => $row->commercial_name,
                    'status' => $row->status,
                    'primary_email' => $row->primary_email,
                    'primary_phone' => $row->primary_phone,
                    'hq_country' => $row->hq_country,
                ];
            });

        return response()->json([
            'data' => $rows,
            'total' => $rows->count(),
        ]);
    }

    /**
     * Retrieve the exact authorized dataset of selected clients for PDF and CSV exports.
     *
     * @param  array<int, string|int>  $ids
     * @return Collection<int, Client>
     */
    public function getSelectedClientsForExport(User $user, array $ids): Collection
    {
        Gate::forUser($user)->authorize('export', Client::class);

        $normalizedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), $ids),
            static fn (string $id) => $id !== ''
        )));

        if ($normalizedIds === []) {
            return new Collection;
        }

        $clients = Client::with('accountManager')
            ->whereIn('id', array_slice($normalizedIds, 0, self::MAX_EXPORT_BATCH))
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($clients as $client) {
            Gate::forUser($user)->authorize('export', $client);
        }

        return $clients;
    }
}
