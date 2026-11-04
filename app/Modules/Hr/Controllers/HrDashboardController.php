<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Services\HrDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    /**
     * Get aggregated HR dashboard statistics and real-time activity across domains.
     */
    public function dashboardStats(Request $request, HrDashboardService $dashboard): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(
            $user->hasRole(['Super Admin', 'Admin', 'HR Manager', 'HR Officer'])
                || $user->can('read-hr-dashboard')
                || $user->can('read-PeopleSkills')
                || $user->can('hr.employees.view')
                || $user->can('hr.attendance.view')
                || $user->can('hr.leave.view')
                || $user->can('hr.fleet.view')
                || $user->can('hr.recruitment.view'),
            403,
            'You do not have permission to view the HR dashboard.'
        );

        return response()->json([
            'data' => $dashboard->statistics($user),
        ]);
    }
}
