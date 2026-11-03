<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientAssetsModuleAccess
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

        abort(403, 'You do not have access to Client Assets.');
    }

    private function canAccess($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($user->can('read-ClientAssets')) {
            return true;
        }

        foreach ([
            'read-client-sites', 'create-client-sites', 'edit-client-sites', 'delete-client-sites',
            'read-client-equipment', 'create-client-equipment', 'edit-client-equipment', 'delete-client-equipment',
            'read-workwear-models', 'create-workwear-models', 'edit-workwear-models', 'delete-workwear-models',
            'read-workwear-employees', 'create-workwear-employees', 'edit-workwear-employees', 'delete-workwear-employees',
            'read-workwear-items', 'create-workwear-items', 'edit-workwear-items', 'delete-workwear-items',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
