<?php

namespace App\Modules\BusinessPartners\Services;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PartnerDashboardService
{
    private const PARTNER_TYPES = [
        'client' => ['table' => 'clients', 'model' => Client::class],
        'supplier' => ['table' => 'suppliers', 'model' => Supplier::class],
        'service_provider' => ['table' => 'service_providers', 'model' => ServiceProvider::class],
        'organisation' => ['table' => 'organisations', 'model' => Organisation::class],
    ];

    /**
     * Build the complete, presentation-ready dashboard payload in one place.
     */
    public function statistics(): array
    {
        $kpis = $this->kpis();

        return [
            'kpis' => $kpis,
            'distribution_ratio' => $this->distributionRatio($kpis),
            'time_series' => $this->timeSeries(),
            'status_breakdown' => $this->statusBreakdown(),
            'recent_partners' => $this->recentPartners(),
        ];
    }

    private function kpis(): array
    {
        return collect(self::PARTNER_TYPES)
            ->mapWithKeys(fn (array $definition, string $type) => [
                $this->pluralType($type) => $this->kpiFor($definition['table']),
            ])
            ->all();
    }

    private function pluralType(string $type): string
    {
        return match ($type) {
            'service_provider' => 'service_providers',
            default => $type.'s',
        };
    }

    private function kpiFor(string $table): array
    {
        $total = DB::table($table)->whereNull('deleted_at')->count();
        $lastThirtyDays = DB::table($table)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $previousThirtyDays = DB::table($table)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        $growth = $previousThirtyDays > 0
            ? (($lastThirtyDays - $previousThirtyDays) / $previousThirtyDays) * 100
            : ($lastThirtyDays > 0 ? 100 : 0);

        return [
            'count' => $total,
            'growth' => round($growth, 1),
            'trend' => $growth >= 0 ? 'up' : 'down',
        ];
    }

    private function distributionRatio(array $kpis): array
    {
        $total = array_sum(array_column($kpis, 'count'));

        return collect($kpis)
            ->map(fn (array $kpi) => $total > 0 ? round(($kpi['count'] / $total) * 100, 1) : 0)
            ->all();
    }

    private function timeSeries(): array
    {
        $months = collect(range(5, 0))
            ->mapWithKeys(function (int $offset) {
                $date = now()->subMonths($offset);

                return [$date->format('Y-m') => [
                    'label' => $date->format('M'),
                    'count' => 0,
                ]];
            });

        foreach (self::PARTNER_TYPES as $definition) {
            DB::table($definition['table'])
                ->select('created_at')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
                ->orderBy('created_at')
                ->each(function (object $partner) use ($months): void {
                    if (! $partner->created_at) {
                        return;
                    }

                    $key = \Carbon\Carbon::parse($partner->created_at)->format('Y-m');
                    if ($months->has($key)) {
                        $month = $months->get($key);
                        $month['count']++;
                        $months->put($key, $month);
                    }
                });
        }

        return [
            'labels' => $months->pluck('label')->values()->all(),
            'series' => $months->pluck('count')->values()->all(),
        ];
    }

    private function statusBreakdown(): array
    {
        $totals = [];

        foreach (self::PARTNER_TYPES as $definition) {
            DB::table($definition['table'])
                ->select('status', DB::raw('COUNT(*) as count'))
                ->whereNull('deleted_at')
                ->groupBy('status')
                ->get()
                ->each(function (object $row) use (&$totals): void {
                    $status = $row->status ?: 'unknown';
                    $totals[$status] = ($totals[$status] ?? 0) + $row->count;
                });
        }

        return collect($totals)
            ->map(fn (int $count, string $status) => ['status' => $status, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Return a small, typed, navigation-safe presentation payload. The logo is
     * resolved once on the server from uploaded media, then the stored external
     * logo, with null deliberately leaving the UI to render initials.
     */
    private function recentPartners(): array
    {
        $queries = collect(self::PARTNER_TYPES)
            ->map(fn (array $definition, string $type) => DB::table($definition['table'])
                ->selectRaw('? as type, id, legal_name as name, primary_email as email, status, updated_at', [$type])
                ->whereNull('deleted_at'))
            ->values();

        $query = $queries->shift();
        foreach ($queries as $partnerQuery) {
            $query->unionAll($partnerQuery);
        }

        $recent = $query
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $models = $this->loadRecentModels($recent);

        return $recent
            ->map(function (object $partner) use ($models): array {
                $model = $models[$partner->type][$partner->id] ?? null;

                return [
                    'id' => $partner->id,
                    'type' => $partner->type,
                    'name' => $model?->getTranslations('legal_name') ?? [],
                    'email' => $partner->email,
                    'logo_url' => $model ? $this->logoUrl($model) : null,
                    'status' => $partner->status,
                    'updated_at' => $partner->updated_at,
                ];
            })
            ->all();
    }

    private function loadRecentModels($recent): array
    {
        return $recent
            ->groupBy('type')
            ->map(function ($partners, string $type) {
                $model = self::PARTNER_TYPES[$type]['model'];

                return $model::query()
                    ->with('media')
                    ->whereIn('id', $partners->pluck('id'))
                    ->get()
                    ->keyBy('id');
            })
            ->all();
    }

    private function logoUrl(Model $partner): ?string
    {
        $mediaUrl = $partner->getFirstMediaUrl('logo');
        if ($mediaUrl) {
            $appUrl = rtrim(config('app.url'), '/');
            $requestBase = rtrim(url('/'), '/');

            return $appUrl !== $requestBase ? str_replace($appUrl, $requestBase, $mediaUrl) : $mediaUrl;
        }

        return $partner->logo_external_url ?: null;
    }
}
