<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmploymentHistory;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\Hr\Models\WorkTeamMember;
use App\Modules\Hr\Requests\StoreEmployeeRequest;
use App\Modules\Hr\Requests\UpdateEmployeeRequest;
use App\Modules\Hr\Resources\EmployeeDetailResource;
use App\Modules\Hr\Resources\EmployeeResource;
use App\Modules\Hr\Services\HrAuditService;
use App\Modules\Shared\Helpers\MediaFormatter;
use App\Modules\Shared\Services\MediaValidationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected HrAuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::with([
            'department:id,name,code',
            'position:id,title,code',
            'currentTeam:id,name,code',
            'manager:id,first_name,last_name,employee_number',
        ]);

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('last_name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%")
                    ->orWhere('personal_email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Department filter
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Position filter
        if ($positionId = $request->input('position_id')) {
            $query->where('position_id', $positionId);
        }

        // Team filter
        if ($teamId = $request->input('team_id')) {
            $query->where('current_team_id', $teamId);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['first_name', 'last_name', 'employee_number', 'hire_date', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy(
                in_array($sortBy, ['first_name', 'last_name']) ? $sortBy.'->'.app()->getLocale() : $sortBy,
                $sortOrder === 'asc' ? 'asc' : 'desc'
            );
        }

        $perPage = (int) $request->input('per_page', 15);
        $employees = $query->paginate($perPage);

        return response()->json([
            'data' => EmployeeResource::collection($employees->getCollection())->resolve(),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json([
            'departments' => Department::active()->select('id', 'name', 'code')->orderBy('name->'.$locale)->get()->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->getTranslations('name'),
                'code' => $department->code,
            ]),
            'positions' => Position::active()->select('id', 'title', 'code', 'department_id')->orderBy('title->'.$locale)->get()->map(fn (Position $position) => [
                'id' => $position->id,
                'title' => $position->getTranslations('title'),
                'code' => $position->code,
                'department_id' => $position->department_id,
            ]),
            'teams' => WorkTeam::active()->select('id', 'name', 'code', 'department_id')->orderBy('name->'.$locale)->get()->map(fn (WorkTeam $team) => [
                'id' => $team->id,
                'name' => $team->getTranslations('name'),
                'code' => $team->code,
                'department_id' => $team->department_id,
            ]),
            'managers' => Employee::active()->select('id', 'user_id', 'first_name', 'last_name', 'employee_number')->orderBy('first_name->'.$locale)->get()->map(fn ($e) => [
                'id' => $e->id,
                'user_id' => $e->user_id,
                'name' => $e->full_name.' ('.$e->employee_number.')',
                'full_name' => $e->full_name,
                'first_name' => $e->getTranslations('first_name'),
                'last_name' => $e->getTranslations('last_name'),
            ]),
            'interviewers' => User::query()
                ->where('is_active', true)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->take(100)
                ->get(),
            'available_users' => User::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereDoesntHave('employee')
                        ->orWhere('id', auth()->id());
                })
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->take(100)
                ->get(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? 'active';
            $employee = Employee::create($data);

            // Record initial employment history
            EmploymentHistory::create([
                'employee_id' => $employee->id,
                'position_id' => $employee->position_id,
                'department_id' => $employee->department_id,
                'team_id' => $employee->current_team_id,
                'manager_id' => $employee->manager_id,
                'employment_type' => 'full_time',
                'contract_type' => 'permanent',
                'start_date' => $employee->hire_date,
                'reason_for_change' => 'Initial hire',
            ]);

            if ($employee->current_team_id) {
                WorkTeamMember::create([
                    'team_id' => $employee->current_team_id,
                    'employee_id' => $employee->id,
                    'is_primary' => true,
                    'joined_at' => $employee->hire_date,
                ]);
            }

            $this->auditService->log($employee, 'CREATE', null, $employee->toArray());

            return $employee;
        });

        return response()->json([
            'message' => 'Employee created successfully.',
            'data' => new EmployeeResource($employee->load(['department', 'position', 'currentTeam', 'manager'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = Employee::with([
            'department',
            'position',
            'manager',
            'currentTeam',
            'user',
            'employmentHistory.position',
            'employmentHistory.department',
            'employmentHistory.team',
            'employmentHistory.manager',
            'teamMemberships.team',
            'employeeSkills.skill',
            'certifications.certification',
            'schedules' => fn ($query) => $query
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->limit(14),
            'attendances',
            'leaveBalances.leaveType',
            'leaveRequests.leaveType',
            'trainings.session.program',
            'appraisals.cycle',
            'appraisals.reviewer',
        ])->findOrFail($id);

        $this->authorize('view', $employee);

        return response()->json([
            'data' => new EmployeeDetailResource($employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $oldData = $employee->toArray();

        DB::transaction(function () use ($request, $employee, $oldData) {
            $data = $request->validated();

            $deptChanged = array_key_exists('department_id', $data) && $data['department_id'] !== $employee->department_id;
            $posChanged = array_key_exists('position_id', $data) && $data['position_id'] !== $employee->position_id;
            $teamChanged = array_key_exists('current_team_id', $data) && $data['current_team_id'] !== $employee->current_team_id;
            $mgrChanged = array_key_exists('manager_id', $data) && $data['manager_id'] !== $employee->manager_id;

            $employee->update($data);

            if ($deptChanged || $posChanged || $teamChanged || $mgrChanged) {
                // Close previous active employment history record
                EmploymentHistory::where('employee_id', $employee->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()->toDateString()]);

                // Create new employment history record
                EmploymentHistory::create([
                    'employee_id' => $employee->id,
                    'position_id' => $employee->position_id,
                    'department_id' => $employee->department_id,
                    'team_id' => $employee->current_team_id,
                    'manager_id' => $employee->manager_id,
                    'employment_type' => 'full_time',
                    'contract_type' => 'permanent',
                    'start_date' => now()->toDateString(),
                    'reason_for_change' => 'Role or team transfer',
                ]);
            }

            if ($teamChanged && $employee->current_team_id) {
                WorkTeamMember::where('employee_id', $employee->id)->update(['left_at' => now()->toDateString()]);
                WorkTeamMember::create([
                    'team_id' => $employee->current_team_id,
                    'employee_id' => $employee->id,
                    'is_primary' => true,
                    'joined_at' => now()->toDateString(),
                ]);
            }

            $this->auditService->log($employee, 'UPDATE', $oldData, $employee->toArray());
        });

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data' => new EmployeeResource($employee->load(['department', 'position', 'currentTeam', 'manager'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('delete', $employee);

        $employee->delete();
        $this->auditService->log($employee, 'DELETE', ['id' => $employee->id]);

        return response()->json([
            'message' => 'Employee deleted successfully.',
        ]);
    }

    public function activate(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $employee->update(['status' => 'active']);

        if ($employee->user_id && ($user = User::find($employee->user_id))) {
            if ($user->account_status !== 'pending_approval') {
                $user->update([
                    'is_active' => true,
                    'can_login' => true,
                    'account_status' => 'active',
                    'is_suspended' => false,
                ]);
            }
        }

        $this->auditService->log($employee, 'ACTIVATE');

        return response()->json([
            'message' => 'Employee activated.',
            'status' => 'active',
        ]);
    }

    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'status' => ['required', 'in:active,on_leave,suspended,terminated'],
            'reason' => ['nullable', 'string'],
            'effective_date' => ['required_if:status,terminated', 'nullable', 'date'],
        ]);

        $oldStatus = $employee->status;
        $newStatus = $validated['status'];
        $effectiveDate = $validated['effective_date'] ?? now()->toDateString();

        DB::transaction(function () use ($employee, $oldStatus, $newStatus, $effectiveDate, $validated) {
            $employee->update([
                'status' => $newStatus,
                'termination_date' => $newStatus === 'terminated' ? $effectiveDate : null,
            ]);

            if ($employee->user_id && ($user = User::find($employee->user_id))) {
                if ($newStatus === 'suspended') {
                    $user->update([
                        'is_active' => false,
                        'can_login' => false,
                        'account_status' => 'suspended',
                        'is_suspended' => true,
                    ]);
                    $user->revokeAllAccess();
                } elseif ($newStatus === 'terminated') {
                    $user->update([
                        'is_active' => false,
                        'can_login' => false,
                        'account_status' => 'inactive',
                        'is_suspended' => false,
                    ]);
                    $user->revokeAllAccess();
                } elseif ($newStatus === 'active' && $user->account_status !== 'pending_approval') {
                    $user->update([
                        'is_active' => true,
                        'can_login' => true,
                        'account_status' => 'active',
                        'is_suspended' => false,
                    ]);
                }
            }

            EmploymentHistory::create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'team_id' => $employee->current_team_id,
                'manager_id' => $employee->manager_id,
                'start_date' => $effectiveDate,
                'reason_for_change' => $validated['reason'] ?? "Status changed to {$newStatus}",
                'notes' => "Status changed from {$oldStatus} to {$newStatus}",
            ]);

            $this->auditService->log($employee, 'CHANGE_STATUS', null, null, $validated['reason'] ?? null);
        });

        return response()->json([
            'message' => "Employee status changed to {$newStatus}.",
            'status' => $newStatus,
        ]);
    }

    public function suspend(Request $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $reason = $request->input('reason', 'Administrative suspension');
        $employee->update(['status' => 'suspended']);

        if ($employee->user_id && ($user = User::find($employee->user_id))) {
            $user->update([
                'is_active' => false,
                'can_login' => false,
                'account_status' => 'suspended',
                'is_suspended' => true,
            ]);
            $user->revokeAllAccess();
        }

        $this->auditService->log($employee, 'SUSPEND', null, null, $reason);

        return response()->json([
            'message' => 'Employee suspended.',
            'status' => 'suspended',
        ]);
    }

    public function terminate(Request $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $request->validate([
            'termination_date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $employee->update([
            'status' => 'terminated',
            'termination_date' => $request->input('termination_date'),
        ]);

        if ($employee->user_id && ($user = User::find($employee->user_id))) {
            $user->update([
                'is_active' => false,
                'can_login' => false,
                'account_status' => 'inactive',
                'is_suspended' => false,
            ]);
            $user->revokeAllAccess();
        }

        $this->auditService->log($employee, 'TERMINATE', null, null, $request->input('reason'));

        return response()->json([
            'message' => 'Employee marked as terminated.',
            'status' => 'terminated',
        ]);
    }

    public function assignTeam(Request $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $request->validate([
            'team_id' => ['ulid', 'required', 'exists:hr_work_teams,id'],
        ]);

        $newTeamId = $request->input('team_id');
        $employee->update(['current_team_id' => $newTeamId]);

        WorkTeamMember::where('employee_id', $employee->id)->update(['left_at' => now()->toDateString()]);
        WorkTeamMember::create([
            'team_id' => $newTeamId,
            'employee_id' => $employee->id,
            'is_primary' => true,
            'joined_at' => now()->toDateString(),
        ]);

        $this->auditService->log($employee, 'ASSIGN_TEAM', null, ['team_id' => $newTeamId]);

        return response()->json([
            'message' => 'Team assigned successfully.',
            'current_team_id' => $newTeamId,
        ]);
    }

    public function storeMedia(Request $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $request->validate([
            'file' => ['required', 'file'],
            'collection' => ['nullable', 'in:avatar,documents'],
        ]);

        $collection = $request->input('collection', 'documents');
        $category = $collection === 'avatar'
            ? MediaValidationService::CATEGORY_IMAGE
            : MediaValidationService::CATEGORY_DOCUMENT;

        $fileMeta = MediaValidationService::validateAndProcess(
            $request->file('file'),
            $category,
            'file'
        );

        $customProperties = [
            'original_name' => $fileMeta['original_name'],
            'uploaded_by' => $fileMeta['uploaded_by'],
            'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            'width' => $fileMeta['width'],
            'height' => $fileMeta['height'],
        ];

        $media = $employee->addMediaFromRequest('file')
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'media' => MediaFormatter::format($media),
        ], 201);
    }

    public function downloadMedia(string $id, string $mediaId)
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('view', $employee);

        $media = $employee->media()->findOrFail($mediaId);

        return $media->toResponse(request());
    }
}
