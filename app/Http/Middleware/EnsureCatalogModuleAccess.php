<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCatalogModuleAccess
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

        abort(403, 'You do not have access to Catalog.');
    }

    private function canAccess($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($user->can('read-Catalog')) {
            return true;
        }

        foreach ([
            'read-catalog-items',
            'create-catalog-items',
            'edit-catalog-items',
            'delete-catalog-items',
            'read-catalog-categories',
            'create-catalog-categories',
            'edit-catalog-categories',
            'delete-catalog-categories',
            'read-catalog-brands',
            'create-catalog-brands',
            'edit-catalog-brands',
            'delete-catalog-brands',
            'read-catalog-uom',
            'create-catalog-uom',
            'edit-catalog-uom',
            'delete-catalog-uom',
            'read-catalog-blueprints',
            'create-catalog-blueprints',
            'edit-catalog-blueprints',
            'delete-catalog-blueprints',
            'read-catalog-guidelines',
            'create-catalog-guidelines',
            'edit-catalog-guidelines',
            'delete-catalog-guidelines',
            'read-catalog-equipment-classes',
            'create-catalog-equipment-classes',
            'edit-catalog-equipment-classes',
            'delete-catalog-equipment-classes',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
