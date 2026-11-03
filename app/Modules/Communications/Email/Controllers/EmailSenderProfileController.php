<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Services\SenderProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmailSenderProfileController extends Controller
{
    public function __construct(
        private readonly SenderProfileService $senderProfileService,
    ) {}

    /**
     * List all active sender profiles that the authenticated user is authorized to use.
     * System profiles are strictly excluded.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailSenderProfile::class);

        $profiles = $this->senderProfileService->getAuthorizedProfiles($request->user());

        return response()->json([
            'data' => $profiles,
        ]);
    }
}
