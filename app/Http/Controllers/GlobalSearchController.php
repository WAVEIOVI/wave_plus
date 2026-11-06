<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Search across multiple models and return grouped results.
     *
     * GET /api/search?q=term
     */
    public function search(Request $request): JsonResponse
    {
        // Authenticated users only
        if (! $request->user()) {
            return response()->json([], 401);
        }

        $query = trim($request->input('q', ''));

        // Require at least 2 characters
        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // ── Search Users ────────────────────────────────────────────────
        $users = User::with('roles')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($users->isNotEmpty()) {
            $results[] = [
                'title' => 'Users',
                'children' => $users->map(fn (User $user) => [
                    'title' => $user->full_name,
                    'subtitle' => $user->email,
                    'icon' => 'tabler-user',
                    'url' => ['name' => 'user-view-id', 'params' => ['id' => $user->id]],
                ])->values()->all(),
            ];
        }

        // ── Search Clients ──────────────────────────────────────────────
        $clients = Client::search($query)->limit(5)->get();

        if ($clients->isNotEmpty()) {
            $results[] = [
                'title' => 'Clients',
                'children' => $clients->map(fn (Client $c) => [
                    'title' => $c->legal_name,
                    'subtitle' => 'Client · '.($c->primary_email ?? $c->client_id),
                    'icon' => 'tabler-users',
                    'url' => ['name' => 'business-partners-clients-edit-id', 'params' => ['id' => $c->id]],
                ])->values()->all(),
            ];
        }

        // ── Search Suppliers ────────────────────────────────────────────
        $suppliers = Supplier::search($query)->limit(5)->get();

        if ($suppliers->isNotEmpty()) {
            $results[] = [
                'title' => 'Suppliers',
                'children' => $suppliers->map(fn (Supplier $s) => [
                    'title' => $s->legal_name,
                    'subtitle' => 'Supplier · '.($s->primary_email ?? $s->supplier_id),
                    'icon' => 'tabler-truck',
                    'url' => ['name' => 'business-partners-suppliers-edit-id', 'params' => ['id' => $s->id]],
                ])->values()->all(),
            ];
        }

        // ── Search Service Providers ────────────────────────────────────
        $serviceProviders = ServiceProvider::search($query)->limit(5)->get();

        if ($serviceProviders->isNotEmpty()) {
            $results[] = [
                'title' => 'Service Providers',
                'children' => $serviceProviders->map(fn (ServiceProvider $sp) => [
                    'title' => $sp->legal_name,
                    'subtitle' => 'Service Provider · '.($sp->primary_email ?? $sp->service_provider_id),
                    'icon' => 'tabler-briefcase',
                    'url' => ['name' => 'business-partners-service-providers-id', 'params' => ['id' => $sp->id]],
                ])->values()->all(),
            ];
        }

        // ── Search Organisations ────────────────────────────────────────
        $organisations = Organisation::search($query)->limit(5)->get();

        if ($organisations->isNotEmpty()) {
            $results[] = [
                'title' => 'Organisations',
                'children' => $organisations->map(fn (Organisation $o) => [
                    'title' => $o->legal_name,
                    'subtitle' => 'Organisation · '.($o->primary_email ?? $o->organisation_id),
                    'icon' => 'tabler-building',
                    'url' => ['name' => 'business-partners-organisations-id', 'params' => ['id' => $o->id]],
                ])->values()->all(),
            ];
        }

        // ── Search Email Conversations ──────────────────────────────────
        $currentUser = $request->user();
        if ($currentUser && ($currentUser->hasRole('Admin') || $currentUser->can('read-Communications') || $currentUser->can('access-email'))) {
            $conversations = \App\Modules\Communications\Email\Models\EmailConversation::query()
                ->where(function ($q) use ($query) {
                    $q->where('subject', 'like', "%{$query}%")
                        ->orWhere('participant_names', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get();

            if ($conversations->isNotEmpty()) {
                $results[] = [
                    'title' => 'Email Conversations',
                    'children' => $conversations->map(fn ($c) => [
                        'title' => $c->subject,
                        'subtitle' => ($c->department ? ucfirst($c->department).' · ' : '').ucfirst($c->status),
                        'icon' => 'tabler-mail',
                        'url' => ['name' => 'communications-email', 'query' => ['filter' => 'inbox', 'q' => $c->subject]],
                    ])->values()->all(),
                ];
            }
        }

        return response()->json($results);
    }
}
