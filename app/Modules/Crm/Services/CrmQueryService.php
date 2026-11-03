<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use Illuminate\Http\Request;

class CrmQueryService
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
}
