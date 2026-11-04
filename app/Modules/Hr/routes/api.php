<?php

use App\Modules\Hr\Controllers\AppraisalController;
use App\Modules\Hr\Controllers\ApprovedDriverController;
use App\Modules\Hr\Controllers\AttendanceController;
use App\Modules\Hr\Controllers\AttendanceExceptionController;
use App\Modules\Hr\Controllers\CertificationController;
use App\Modules\Hr\Controllers\DepartmentController;
use App\Modules\Hr\Controllers\EmployeeController;
use App\Modules\Hr\Controllers\FleetAssignmentController;
use App\Modules\Hr\Controllers\FleetChecklistController;
use App\Modules\Hr\Controllers\FleetDocumentController;
use App\Modules\Hr\Controllers\FleetFuelRecordController;
use App\Modules\Hr\Controllers\FleetMaintenanceRecordController;
use App\Modules\Hr\Controllers\FleetVehicleController;
use App\Modules\Hr\Controllers\HrDashboardController;
use App\Modules\Hr\Controllers\LeavePolicyController;
use App\Modules\Hr\Controllers\LeaveRequestController;
use App\Modules\Hr\Controllers\LeaveTypeController;
use App\Modules\Hr\Controllers\PositionController;
use App\Modules\Hr\Controllers\PublicHolidayController;
use App\Modules\Hr\Controllers\RecruitmentController;
use App\Modules\Hr\Controllers\SkillController;
use App\Modules\Hr\Controllers\TimeClockController;
use App\Modules\Hr\Controllers\TrainingController;
use App\Modules\Hr\Controllers\WorkforceAvailabilityController;
use App\Modules\Hr\Controllers\WorkforcePlannerController;
use App\Modules\Hr\Controllers\WorkTeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR / People & Skills API Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/hr and guarded by auth:sanctum.
|
*/

Route::prefix('hr')
    ->middleware(['auth:sanctum', 'hr.access'])
    ->group(function () {

        // ── Dashboard KPIs ──────────────────────────────────────────
        Route::get('dashboard-stats', [HrDashboardController::class, 'dashboardStats']);

        // ── Employees ───────────────────────────────────────────────
        Route::get('employees/meta', [EmployeeController::class, 'meta']);
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::get('employees/{id}', [EmployeeController::class, 'show']);
        Route::put('employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('employees/{id}', [EmployeeController::class, 'destroy']);
        Route::post('employees/{id}/activate', [EmployeeController::class, 'activate']);
        Route::post('employees/{id}/suspend', [EmployeeController::class, 'suspend']);
        Route::post('employees/{id}/terminate', [EmployeeController::class, 'terminate']);
        Route::post('employees/{id}/change-status', [EmployeeController::class, 'changeStatus']);
        Route::post('employees/{id}/assign-team', [EmployeeController::class, 'assignTeam']);
        Route::post('employees/{id}/media', [EmployeeController::class, 'storeMedia']);
        Route::get('employees/{id}/media/{mediaId}/download', [EmployeeController::class, 'downloadMedia']);

        // Fleet master data is owned by People & Skills / Resource Management.
        Route::get('fleet/vehicles/alerts', [FleetVehicleController::class, 'alerts']);
        Route::get('fleet/vehicles', [FleetVehicleController::class, 'index']);
        Route::post('fleet/vehicles', [FleetVehicleController::class, 'store']);
        Route::get('fleet/vehicles/{vehicle}', [FleetVehicleController::class, 'show']);
        Route::put('fleet/vehicles/{vehicle}', [FleetVehicleController::class, 'update']);
        Route::delete('fleet/vehicles/{vehicle}', [FleetVehicleController::class, 'destroy']);
        Route::post('fleet/vehicles/{vehicle}/assign', [FleetVehicleController::class, 'assign']);
        Route::post('fleet/vehicles/{vehicle}/return', [FleetVehicleController::class, 'returnVehicle']);
        Route::patch('fleet/vehicles/{vehicle}/status', [FleetVehicleController::class, 'updateStatus']);
        Route::patch('fleet/vehicles/{vehicle}/odometer', [FleetVehicleController::class, 'updateOdometer']);
        Route::post('fleet/vehicles/{vehicle}/telemetry', [FleetVehicleController::class, 'updateTelemetry']);
        Route::post('fleet/vehicles/{vehicle}/rental/start', [FleetVehicleController::class, 'startRental']);
        Route::post('fleet/vehicles/{vehicle}/rental/end', [FleetVehicleController::class, 'endRental']);
        Route::post('fleet/vehicles/{vehicle}/client-usage/start', [FleetVehicleController::class, 'startClientUsage']);
        Route::post('fleet/vehicles/{vehicle}/client-usage/end', [FleetVehicleController::class, 'endClientUsage']);
        Route::get('fleet/vehicles/{vehicle}/activity-log', [FleetVehicleController::class, 'activityLog']);
        Route::get('fleet/vehicles/{vehicle}/utilization', [FleetVehicleController::class, 'utilization']);

        // ── Fleet Child Resources (Assignments, Documents, Fuel, Maintenance, Checklists) ──
        Route::get('fleet/assignments', [FleetAssignmentController::class, 'index']);
        Route::get('fleet/assignments/{assignment}', [FleetAssignmentController::class, 'show']);
        Route::get('fleet/vehicles/{vehicle}/assignments', [FleetAssignmentController::class, 'index']);

        Route::get('fleet/vehicles/{vehicle}/documents', [FleetDocumentController::class, 'index']);
        Route::post('fleet/vehicles/{vehicle}/documents', [FleetDocumentController::class, 'store']);
        Route::get('fleet/documents/{document}', [FleetDocumentController::class, 'show']);
        Route::get('fleet/documents/{document}/download', [FleetDocumentController::class, 'download']);
        Route::put('fleet/documents/{document}', [FleetDocumentController::class, 'update']);
        Route::delete('fleet/documents/{document}', [FleetDocumentController::class, 'destroy']);

        Route::get('fleet/vehicles/{vehicle}/fuel-records', [FleetFuelRecordController::class, 'index']);
        Route::post('fleet/vehicles/{vehicle}/fuel-records', [FleetFuelRecordController::class, 'store']);
        Route::get('fleet/fuel-records/{fuelRecord}', [FleetFuelRecordController::class, 'show']);
        Route::put('fleet/fuel-records/{fuelRecord}', [FleetFuelRecordController::class, 'update']);
        Route::delete('fleet/fuel-records/{fuelRecord}', [FleetFuelRecordController::class, 'destroy']);

        Route::get('fleet/maintenance-records', [FleetMaintenanceRecordController::class, 'index']);
        Route::get('fleet/vehicles/{vehicle}/maintenance-records', [FleetMaintenanceRecordController::class, 'index']);
        Route::post('fleet/vehicles/{vehicle}/maintenance-records', [FleetMaintenanceRecordController::class, 'store']);
        Route::get('fleet/maintenance-records/{maintenanceRecord}', [FleetMaintenanceRecordController::class, 'show']);
        Route::put('fleet/maintenance-records/{maintenanceRecord}', [FleetMaintenanceRecordController::class, 'update']);
        Route::delete('fleet/maintenance-records/{maintenanceRecord}', [FleetMaintenanceRecordController::class, 'destroy']);

        Route::get('fleet/checklists', [FleetChecklistController::class, 'index']);
        Route::get('fleet/vehicles/{vehicle}/checklists', [FleetChecklistController::class, 'index']);
        Route::post('fleet/vehicles/{vehicle}/checklists', [FleetChecklistController::class, 'store']);
        Route::get('fleet/checklists/{checklist}', [FleetChecklistController::class, 'show']);
        Route::put('fleet/checklists/{checklist}', [FleetChecklistController::class, 'update']);
        Route::delete('fleet/checklists/{checklist}', [FleetChecklistController::class, 'destroy']);

        // ── Departments ─────────────────────────────────────────────
        Route::get('departments', [DepartmentController::class, 'index']);
        Route::post('departments', [DepartmentController::class, 'store']);
        Route::get('departments/{id}', [DepartmentController::class, 'show']);
        Route::put('departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('departments/{id}', [DepartmentController::class, 'destroy']);

        // ── Positions ───────────────────────────────────────────────
        Route::get('positions', [PositionController::class, 'index']);
        Route::post('positions', [PositionController::class, 'store']);
        Route::get('positions/{id}', [PositionController::class, 'show']);
        Route::put('positions/{id}', [PositionController::class, 'update']);
        Route::delete('positions/{id}', [PositionController::class, 'destroy']);

        // ── Work Teams ──────────────────────────────────────────────
        Route::get('teams', [WorkTeamController::class, 'index']);
        Route::post('teams', [WorkTeamController::class, 'store']);
        Route::get('teams/{id}', [WorkTeamController::class, 'show']);
        Route::put('teams/{id}', [WorkTeamController::class, 'update']);
        Route::delete('teams/{id}', [WorkTeamController::class, 'destroy']);
        Route::post('teams/{id}/members', [WorkTeamController::class, 'addMember']);
        Route::delete('teams/{id}/members/{memberId}', [WorkTeamController::class, 'removeMember']);

        // ── Skills & Certifications ─────────────────────────────────
        Route::get('skills', [SkillController::class, 'index']);
        Route::post('skills', [SkillController::class, 'store']);
        Route::put('skills/{id}', [SkillController::class, 'update']);
        Route::delete('skills/{id}', [SkillController::class, 'destroy']);
        Route::post('employees/{employeeId}/skills', [SkillController::class, 'assignToEmployee']);
        Route::put('employee-skills/{id}', [SkillController::class, 'updateEmployeeSkill']);
        Route::delete('employee-skills/{id}', [SkillController::class, 'destroyEmployeeSkill']);
        Route::put('employee-skills/{id}/verify', [SkillController::class, 'verifyEmployeeSkill']);
        Route::get('employees/{employeeId}/development-summary', [SkillController::class, 'employeeDevelopmentSummary']);

        Route::get('certifications/expiring', [CertificationController::class, 'expiringCertifications']);
        Route::get('certifications', [CertificationController::class, 'index']);
        Route::post('certifications', [CertificationController::class, 'store']);
        Route::put('certifications/{id}', [CertificationController::class, 'update']);
        Route::delete('certifications/{id}', [CertificationController::class, 'destroy']);
        Route::post('employees/{employeeId}/certifications', [CertificationController::class, 'assignToEmployee']);
        Route::put('employee-certifications/{id}', [CertificationController::class, 'updateEmployeeCertification']);
        Route::delete('employee-certifications/{id}', [CertificationController::class, 'destroyEmployeeCertification']);
        Route::put('employee-certifications/{id}/verify', [CertificationController::class, 'verifyEmployeeCertification']);
        Route::get('employee-certifications/{id}/download', [CertificationController::class, 'downloadCertificationFile']);
        Route::get('employee-certifications/{id}/media/{mediaId}/download', [CertificationController::class, 'downloadCertificationFile']);
        Route::get('certifications/employee/{id}/download', [CertificationController::class, 'downloadCertificationFile']);

        // ── Workforce Availability & Scheduling ─────────────────────
        Route::post('workforce/query-availability', [WorkforceAvailabilityController::class, 'queryAvailability']);
        Route::get('workforce/shifts', [WorkforceAvailabilityController::class, 'shifts']);
        Route::post('workforce/shifts', [WorkforceAvailabilityController::class, 'storeShift']);
        Route::put('workforce/shifts/{id}', [WorkforceAvailabilityController::class, 'updateShift']);
        Route::delete('workforce/shifts/{id}', [WorkforceAvailabilityController::class, 'destroyShift']);
        Route::get('workforce/schedules', [WorkforceAvailabilityController::class, 'schedules']);
        Route::post('workforce/schedules', [WorkforceAvailabilityController::class, 'storeSchedule']);
        Route::put('workforce/schedules/{id}', [WorkforceAvailabilityController::class, 'updateSchedule']);
        Route::delete('workforce/schedules/{id}', [WorkforceAvailabilityController::class, 'destroySchedule']);

        // Workforce Planner Grid & Bulk Operations
        Route::get('workforce/planner-grid', [WorkforcePlannerController::class, 'plannerGrid']);
        Route::post('workforce/bulk-schedule', [WorkforcePlannerController::class, 'bulkSchedule']);
        Route::post('workforce/publish-schedules', [WorkforcePlannerController::class, 'publishSchedules']);
        Route::post('workforce/clear-schedules', [WorkforcePlannerController::class, 'clearSchedules']);
        Route::get('workforce/schedule-history', [WorkforcePlannerController::class, 'scheduleHistory']);

        // Public Holidays (Integrated with Workforce & Time Off)
        Route::get('public-holidays', [PublicHolidayController::class, 'index']);
        Route::post('public-holidays', [PublicHolidayController::class, 'store']);
        Route::get('public-holidays/{id}', [PublicHolidayController::class, 'show']);
        Route::put('public-holidays/{id}', [PublicHolidayController::class, 'update']);
        Route::delete('public-holidays/{id}', [PublicHolidayController::class, 'destroy']);

        // ── Time Clock & Attendance ─────────────────────────────────
        Route::get('time-clock/today-status', [TimeClockController::class, 'todayStatus']);
        Route::post('time-clock/preview-context', [TimeClockController::class, 'previewContext']);
        Route::post('time-clock/clock-in', [TimeClockController::class, 'clockIn'])->middleware('throttle:30,1');
        Route::post('time-clock/clock-out', [TimeClockController::class, 'clockOut'])->middleware('throttle:30,1');
        Route::get('time-clock/badge/{employeeId}', [TimeClockController::class, 'badge']);
        Route::post('time-clock/scan', [TimeClockController::class, 'scan'])->middleware('throttle:60,1');
        Route::post('time-clock/bulk', [TimeClockController::class, 'bulk']);

        // Attendance Configuration & Timeline
        Route::get('attendance-config', [AttendanceController::class, 'getConfig']);
        Route::put('attendance-config', [AttendanceController::class, 'updateConfig']);
        Route::get('attendances/timeline', [AttendanceController::class, 'timeline']);

        Route::get('attendances', [AttendanceController::class, 'index']);
        Route::post('attendances', [AttendanceController::class, 'store']);
        Route::get('attendances/{id}', [AttendanceController::class, 'show']);
        Route::put('attendances/{id}', [AttendanceController::class, 'update']);
        Route::delete('attendances/{id}', [AttendanceController::class, 'destroy']);
        Route::post('attendances/{id}/request-correction', [AttendanceController::class, 'requestCorrection']);
        Route::post('attendance-corrections/{correctionId}/decide', [AttendanceController::class, 'decideCorrection']);

        // ── Attendance Exceptions & Reconciliation ──────────────────
        Route::get('attendance-exceptions', [AttendanceExceptionController::class, 'index']);
        Route::post('attendance-exceptions/{id}/resolve', [AttendanceExceptionController::class, 'resolve']);
        Route::post('attendance-exceptions/{id}/dismiss', [AttendanceExceptionController::class, 'dismiss']);
        Route::post('attendance-exceptions/{id}/acknowledge', [AttendanceExceptionController::class, 'acknowledge']);

        // ── Time Off & Leave ────────────────────────────────────────
        Route::get('leave-types', [LeaveTypeController::class, 'index']);
        Route::post('leave-types', [LeaveTypeController::class, 'store']);
        Route::get('leave-types/{id}', [LeaveTypeController::class, 'show']);
        Route::put('leave-types/{id}', [LeaveTypeController::class, 'update']);
        Route::delete('leave-types/{id}', [LeaveTypeController::class, 'destroy']);
        Route::get('leave-balances', [LeaveTypeController::class, 'balances']);
        Route::post('leave-balances/adjust', [LeaveTypeController::class, 'adjustBalance']);
        Route::get('leave-calendar', [LeaveRequestController::class, 'calendar']);

        Route::get('leave-policies', [LeavePolicyController::class, 'index']);
        Route::post('leave-policies', [LeavePolicyController::class, 'store']);
        Route::get('leave-policies/{id}', [LeavePolicyController::class, 'show']);
        Route::put('leave-policies/{id}', [LeavePolicyController::class, 'update']);
        Route::delete('leave-policies/{id}', [LeavePolicyController::class, 'destroy']);

        Route::get('leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('leave-requests', [LeaveRequestController::class, 'store']);
        Route::get('leave-requests/{id}', [LeaveRequestController::class, 'show']);
        Route::put('leave-requests/{id}', [LeaveRequestController::class, 'update']);
        Route::delete('leave-requests/{id}', [LeaveRequestController::class, 'destroy']);
        Route::post('leave-requests/{id}/submit', [LeaveRequestController::class, 'submit']);
        Route::post('leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
        Route::post('leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel']);

        // ── Recruitment ─────────────────────────────────────────────
        Route::get('recruitment/openings', [RecruitmentController::class, 'jobOpenings']);
        Route::post('recruitment/openings', [RecruitmentController::class, 'storeJobOpening']);
        Route::get('recruitment/openings/{id}', [RecruitmentController::class, 'showJobOpening']);
        Route::put('recruitment/openings/{id}', [RecruitmentController::class, 'updateJobOpening']);
        Route::delete('recruitment/openings/{id}', [RecruitmentController::class, 'destroyJobOpening']);
        Route::get('recruitment/candidates', [RecruitmentController::class, 'candidates']);
        Route::post('recruitment/candidates', [RecruitmentController::class, 'storeCandidate']);
        Route::get('recruitment/candidates/{id}', [RecruitmentController::class, 'showCandidate']);
        Route::get('recruitment/candidates/{id}/resume/download', [RecruitmentController::class, 'downloadResume']);
        Route::get('recruitment/candidates/{id}/media/{mediaId}/download', [RecruitmentController::class, 'downloadResume']);
        Route::post('recruitment/candidates/{id}/media', [RecruitmentController::class, 'storeMedia']);
        Route::delete('recruitment/candidates/{id}/media/{mediaId}', [RecruitmentController::class, 'destroyMedia']);
        Route::put('recruitment/candidates/{id}', [RecruitmentController::class, 'updateCandidate']);
        Route::delete('recruitment/candidates/{id}', [RecruitmentController::class, 'destroyCandidate']);
        Route::post('recruitment/candidates/{candidateId}/interviews', [RecruitmentController::class, 'scheduleInterview']);
        Route::put('recruitment/interviews/{id}', [RecruitmentController::class, 'updateInterview']);
        Route::patch('recruitment/candidates/{candidateId}/stage', [RecruitmentController::class, 'updateStage']);
        Route::post('recruitment/candidates/{candidateId}/hire', [RecruitmentController::class, 'hireCandidate']);

        // ── Training ────────────────────────────────────────────────
        Route::get('training/programs', [TrainingController::class, 'programs']);
        Route::post('training/programs', [TrainingController::class, 'storeProgram']);
        Route::get('training/programs/{id}', [TrainingController::class, 'showProgram']);
        Route::put('training/programs/{id}', [TrainingController::class, 'updateProgram']);
        Route::delete('training/programs/{id}', [TrainingController::class, 'destroyProgram']);
        Route::get('training/sessions', [TrainingController::class, 'sessions']);
        Route::post('training/sessions', [TrainingController::class, 'storeSession']);
        Route::get('training/sessions/{id}', [TrainingController::class, 'showSession']);
        Route::put('training/sessions/{id}', [TrainingController::class, 'updateSession']);
        Route::delete('training/sessions/{id}', [TrainingController::class, 'destroySession']);
        Route::post('training/sessions/{sessionId}/enroll', [TrainingController::class, 'enrollEmployee']);
        Route::post('training/sessions/{sessionId}/batch-attendance', [TrainingController::class, 'batchAttendance']);
        Route::delete('training/participants/{participantId}', [TrainingController::class, 'destroyParticipant']);
        Route::post('training/participants/{participantId}/complete', [TrainingController::class, 'completeTraining']);

        // ── Performance Appraisals ──────────────────────────────────
        Route::get('appraisals/kpis', [AppraisalController::class, 'kpis']);
        Route::get('appraisals/cycles', [AppraisalController::class, 'cycles']);
        Route::post('appraisals/cycles', [AppraisalController::class, 'storeCycle']);
        Route::get('appraisals/cycles/{id}', [AppraisalController::class, 'showCycle']);
        Route::put('appraisals/cycles/{id}', [AppraisalController::class, 'updateCycle']);
        Route::delete('appraisals/cycles/{id}', [AppraisalController::class, 'destroyCycle']);
        Route::get('appraisals', [AppraisalController::class, 'appraisals']);
        Route::post('appraisals', [AppraisalController::class, 'storeAppraisal']);
        Route::post('appraisals/{id}/acknowledge', [AppraisalController::class, 'acknowledge']);
        Route::get('appraisals/{id}', [AppraisalController::class, 'showAppraisal']);
        Route::put('appraisals/{id}', [AppraisalController::class, 'updateAppraisal']);
        Route::delete('appraisals/{id}', [AppraisalController::class, 'destroyAppraisal']);

        // ── Approved Drivers (Fleet Authorization) ──────────────────
        Route::get('approved-drivers/requires-license-renewal', [ApprovedDriverController::class, 'requiresLicenseRenewal']);
        Route::get('approved-drivers/requires-review', [ApprovedDriverController::class, 'requiresReview']);
        Route::get('approved-drivers', [ApprovedDriverController::class, 'index']);
        Route::post('approved-drivers', [ApprovedDriverController::class, 'store']);
        Route::get('approved-drivers/{driver}', [ApprovedDriverController::class, 'show']);
        Route::put('approved-drivers/{driver}', [ApprovedDriverController::class, 'update']);
        Route::delete('approved-drivers/{driver}', [ApprovedDriverController::class, 'destroy']);

        // Driver operations
        Route::post('approved-drivers/{driver}/suspend', [ApprovedDriverController::class, 'suspend']);
        Route::post('approved-drivers/{driver}/reinstate', [ApprovedDriverController::class, 'reinstate']);
        Route::post('approved-drivers/{driver}/revoke', [ApprovedDriverController::class, 'revoke']);
        Route::post('approved-drivers/{driver}/renew', [ApprovedDriverController::class, 'renew']);
        Route::post('approved-drivers/{driver}/incidents', [ApprovedDriverController::class, 'recordIncident']);
        Route::get('approved-drivers/{driver}/activity-log', [ApprovedDriverController::class, 'activityLog']);
        Route::get('approved-drivers/{driver}/calculate-score', [ApprovedDriverController::class, 'calculateScore']);
        Route::post('approved-drivers/{driver}/adjust-score', [ApprovedDriverController::class, 'adjustScore']);
    });
