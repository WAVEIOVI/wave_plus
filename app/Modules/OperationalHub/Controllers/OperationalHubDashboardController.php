<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Enums\MissionStatus;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\Finding;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\OperationalForecast;
use App\Modules\OperationalHub\Models\ServiceDeliveryNote;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Resources\MissionListResource;
use App\Modules\OperationalHub\Resources\ServiceWorkOrderListResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalHubDashboardController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceWorkOrder::class);

        $totalSwos = ServiceWorkOrder::count();
        $inProgressSwos = ServiceWorkOrder::where('status', SwoStatus::InProgress->value)->count();
        $completedSwos = ServiceWorkOrder::where('status', SwoStatus::Completed->value)->count();
        $activeMissions = Mission::whereIn('status', [MissionStatus::Started->value, MissionStatus::InProgress->value])->count();
        $openFindings = Finding::where('status', 'open')->count();
        $totalSdns = ServiceDeliveryNote::count();

        // Mode breakdown
        $fieldServiceCount = ServiceWorkOrder::where('fulfillment_mode', SwoFulfillmentMode::FieldService->value)->count();
        $workshopCount = ServiceWorkOrder::where('fulfillment_mode', SwoFulfillmentMode::Workshop->value)->count();

        // Upcoming missions
        $upcomingMissions = Mission::query()
            ->whereDate('planned_date', '>=', now()->toDateString())
            ->whereIn('status', [MissionStatus::Planned->value, MissionStatus::Ready->value, MissionStatus::Started->value])
            ->with(['swo.client', 'team', 'leadTechnician', 'serviceSite', 'workshopUnit'])
            ->orderBy('planned_date', 'asc')
            ->limit(5)
            ->get();

        // Recent SWOs
        $recentSwos = ServiceWorkOrder::query()
            ->with(['client', 'team', 'leadTechnician', 'salesOrder'])
            ->latest()
            ->limit(5)
            ->get();

        // Status counts for chart
        $statusDistribution = [
            'draft'               => ServiceWorkOrder::where('status', SwoStatus::Draft->value)->count(),
            'planned'             => ServiceWorkOrder::where('status', SwoStatus::Planned->value)->count(),
            'scheduled'           => ServiceWorkOrder::where('status', SwoStatus::Scheduled->value)->count(),
            'in_progress'         => $inProgressSwos,
            'partially_completed' => ServiceWorkOrder::where('status', SwoStatus::PartiallyCompleted->value)->count(),
            'completed'           => $completedSwos,
            'closed'              => ServiceWorkOrder::where('status', SwoStatus::Closed->value)->count(),
            'cancelled'           => ServiceWorkOrder::where('status', SwoStatus::Cancelled->value)->count(),
        ];

        return response()->json([
            'data' => [
                'stats' => [
                    'total_swos'          => $totalSwos,
                    'in_progress_swos'    => $inProgressSwos,
                    'completed_swos'      => $completedSwos,
                    'active_missions'     => $activeMissions,
                    'open_findings'       => $openFindings,
                    'total_sdns'          => $totalSdns,
                    'field_service_swos'  => $fieldServiceCount,
                    'workshop_swos'       => $workshopCount,
                ],
                'status_distribution' => $statusDistribution,
                'upcoming_missions'   => MissionListResource::collection($upcomingMissions),
                'recent_swos'         => ServiceWorkOrderListResource::collection($recentSwos),
            ],
        ]);
    }
}
