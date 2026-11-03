<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventoryModuleAccess
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

        abort(403, 'You do not have access to Inventory & Warehouse.');
    }

    private function canAccess($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($user->can('read-InventoryWarehouse')) {
            return true;
        }

        foreach ([
            'read-inventory-stock-items',
            'read-inventory-movements', 'create-inventory-movements',
            'read-inventory-sites', 'create-inventory-sites', 'edit-inventory-sites', 'delete-inventory-sites',
            'read-inventory-zones', 'create-inventory-zones', 'edit-inventory-zones', 'delete-inventory-zones',
            'read-inventory-locations', 'create-inventory-locations', 'edit-inventory-locations', 'delete-inventory-locations',
            'read-inventory-lots', 'create-inventory-lots', 'edit-inventory-lots', 'delete-inventory-lots',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
