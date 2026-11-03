<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProcurementModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($this->canAccess($user)) {
            return $next($request);
        }

        abort(403, 'You do not have access to Supply Chain & Procurement.');
    }

    private function canAccess($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($user->can('read-SupplyChainProcurement')) {
            return true;
        }

        foreach ([
            'read-procurement-iprs', 'create-procurement-iprs', 'edit-procurement-iprs', 'delete-procurement-iprs', 'approve-procurement-iprs',
            'read-procurement-rfqs', 'create-procurement-rfqs', 'edit-procurement-rfqs', 'delete-procurement-rfqs', 'award-procurement-rfqs',
            'read-procurement-pos', 'create-procurement-pos', 'edit-procurement-pos', 'delete-procurement-pos', 'approve-procurement-pos',
            'read-procurement-grns', 'create-procurement-grns', 'edit-procurement-grns', 'delete-procurement-grns',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
