<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Enums\MissionMode;
use App\Modules\OperationalHub\Enums\MissionStatus;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\WorkshopReceptionStatus;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Models\WorkshopReception;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use App\Modules\OperationalHub\Resources\MissionListResource;
use App\Modules\OperationalHub\Resources\WorkshopReceptionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopDashboardController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkshopReception::class);

        $totalUnits = WorkshopUnit::count();
        $activeUnits = WorkshopUnit::where('status', 'active')->count();
        $totalReceptions = WorkshopReception::count();
        $pendingReceptions = WorkshopReception::whereIn('status', [
            WorkshopReceptionStatus::Pending->value,
            WorkshopReceptionStatus::Received->value,
            WorkshopReceptionStatus::InDiagnosis->value,
        ])->count();

        $activeWorkshopMissions = Mission::where('mode', MissionMode::Workshop->value)
            ->whereIn('status', [MissionStatus::Started->value, MissionStatus::InProgress->value])
            ->count();

        $completedWorkshopMissions = Mission::where('mode', MissionMode::Workshop->value)
            ->where('status', MissionStatus::Completed->value)
            ->count();

        $recentReceptions = WorkshopReception::query()
            ->with(['workshopUnit', 'equipment', 'client'])
            ->latest()
            ->limit(5)
            ->get();

        $activeMissions = Mission::query()
            ->where('mode', MissionMode::Workshop->value)
            ->with(['workshopUnit', 'leadTechnician', 'swo.client'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'stats' => [
                    'total_units'                 => $totalUnits,
                    'active_units'                => $activeUnits,
                    'total_receptions'            => $totalReceptions,
                    'pending_receptions'          => $pendingReceptions,
                    'active_workshop_missions'    => $activeWorkshopMissions,
                    'completed_workshop_missions' => $completedWorkshopMissions,
                ],
                'recent_receptions' => WorkshopReceptionResource::collection($recentReceptions),
                'active_missions'   => MissionListResource::collection($activeMissions),
            ],
        ]);
    }
}
