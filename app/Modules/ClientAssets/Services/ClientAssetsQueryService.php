<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Services;

use Illuminate\Http\Request;

class ClientAssetsQueryService
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

    public function safeSort(Request $request, array $allowed, string $default): string
    {
        $sortBy = (string) $request->input('sortBy', $default);

        return in_array($sortBy, $allowed, true) ? $sortBy : $default;
    }

    public function sortDirection(Request $request, string $default = 'desc'): string
    {
        return strtolower((string) $request->input('orderBy', $default)) === 'asc' ? 'asc' : 'desc';
    }
}
