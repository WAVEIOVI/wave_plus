<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBpModuleAccess
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

        abort(403, 'You do not have access to Business Partners.');
    }

    private function canAccess($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($user->can('read-BusinessPartners')) {
            return true;
        }

        foreach ([
            'read-clients',
            'create-clients',
            'edit-clients',
            'delete-clients',
            'export-clients',
            'read-suppliers',
            'create-suppliers',
            'edit-suppliers',
            'delete-suppliers',
            'export-suppliers',
            'read-service-providers',
            'create-service-providers',
            'edit-service-providers',
            'delete-service-providers',
            'export-service-providers',
            'read-organisations',
            'create-organisations',
            'edit-organisations',
            'delete-organisations',
            'export-organisations',
            'read-bp-dashboard',
            'read-bp-bank-accounts',
            'manage-bp-bank-accounts',
            'view-all-bp-email-batches',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
