<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Services\EmailDocumentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailDocumentController extends Controller
{
    public function __construct(
        private readonly EmailDocumentSearchService $documents,
    ) {}

    /**
     * Search existing, authorized documents that can be attached to an email.
     * Never exposes storage paths or URLs.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole(['Super Admin', 'Admin', 'Responsable']) || $user->can('create-email-messages'),
            403,
            'Unauthorized to attach documents.'
        );

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'query' => 'nullable|string|max:100',
            'module' => 'nullable|string|in:business_partners',
            'entity_type' => 'nullable|string|in:client,supplier,organisation,service_provider',
            'category' => 'nullable|string|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:48',
        ]);

        if (empty($validated['q']) && ! empty($validated['query'])) {
            $validated['q'] = $validated['query'];
        }

        $result = $this->documents->search(
            $user,
            $validated,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 12),
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => ['total' => $result['total']],
        ]);
    }
}
