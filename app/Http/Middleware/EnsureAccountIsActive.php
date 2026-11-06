<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * Enforce account deactivation on all authenticated requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAuthorizedToLogin()) {
            // Revoke active sessions and tokens
            $user->revokeAllAccess();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Your account is deactivated or suspended. Please contact an administrator.',
                'code' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        return $next($request);
    }
}
