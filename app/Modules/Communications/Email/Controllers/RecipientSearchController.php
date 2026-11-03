<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Services\RecipientSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipientSearchController extends Controller
{
    public function __construct(
        private readonly RecipientSearchService $recipientSearchService,
    ) {}

    /**
     * Search across Business Partners, Employees, and Manual email entries.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check base authorization
        $canAccess = $user->hasRole(['Super Admin', 'Admin', 'Responsable'])
            || $user->can('read-Communications')
            || $user->can('create-email-messages');

        if (! $canAccess) {
            abort(403, 'Unauthorized to search email recipients.');
        }

        $query = trim((string) ($request->input('q') ?? $request->input('query') ?? ''));
        $type = $request->input('type') ?? $request->input('category');
        $source = $request->input('source') ?? $request->input('email_source');
        $limit = $request->integer('limit', 50);

        if ($request->boolean('grouped')) {
            $rawTypes = $request->input('types') ?? $type ?? [];
            if (is_string($rawTypes)) {
                $rawTypes = array_filter(array_map('trim', explode(',', $rawTypes)));
            }
            $types = is_array($rawTypes) ? $rawTypes : [$rawTypes];

            $rawDepts = $request->input('departments') ?? $request->input('department_ids');
            $departments = null;
            if ($rawDepts) {
                if (is_string($rawDepts)) {
                    $rawDepts = array_filter(array_map('trim', explode(',', $rawDepts)));
                }
                $departments = is_array($rawDepts) ? $rawDepts : [$rawDepts];
            }

            $results = $this->recipientSearchService->searchGrouped(
                $user,
                $query,
                $types,
                $departments,
                $limit
            );

            return response()->json([
                'data' => $results,
            ]);
        }

        $results = $this->recipientSearchService->search(
            $user,
            $query,
            is_string($type) ? $type : null,
            is_string($source) ? $source : null,
            $limit
        );

        return response()->json([
            'data' => $results,
        ]);
    }

    /**
     * List active HR departments for email recipient filtering.
     */
    public function departments(Request $request): JsonResponse
    {
        $user = $request->user();

        $canAccess = $user->hasRole(['Super Admin', 'Admin', 'Responsable'])
            || $user->can('read-Communications')
            || $user->can('create-email-messages')
            || $user->can('read-PeopleSkills');

        if (! $canAccess) {
            abort(403, 'Unauthorized to view departments.');
        }

        $departments = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('hr_departments')) {
            $departments = \App\Modules\Hr\Models\Department::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($d) => [
                    'id' => (string) $d->id,
                    'name' => (string) $d->name,
                    'code' => $d->code,
                ]);
        }

        return response()->json([
            'data' => $departments,
        ]);
    }
}
