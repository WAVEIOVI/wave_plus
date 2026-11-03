<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmModuleAccess
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

        abort(403, 'You do not have access to CRM & Sales.');
    }

    private function canAccess($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($user->can('read-CrmSales')) {
            return true;
        }

        foreach ([
            'read-crm-quotations', 'create-crm-quotations', 'edit-crm-quotations', 'delete-crm-quotations', 'approve-crm-quotations',
            'read-crm-sales-orders', 'create-crm-sales-orders', 'edit-crm-sales-orders', 'delete-crm-sales-orders', 'approve-crm-sales-orders',
            'read-crm-contracts', 'create-crm-contracts', 'edit-crm-contracts', 'delete-crm-contracts',
            'read-crm-price-requests', 'create-crm-price-requests', 'edit-crm-price-requests', 'delete-crm-price-requests',
            'read-crm-price-lists', 'create-crm-price-lists', 'edit-crm-price-lists', 'delete-crm-price-lists',
            'read-crm-lsa-documents', 'read-crm-gcs-documents',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
