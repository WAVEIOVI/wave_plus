<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\Hr\Models\WorkTeamMember;
use App\Modules\Hr\Resources\WorkTeamResource;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkTeamController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'team_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'department_id' => ['sometimes', 'nullable', 'ulid', 'exists:hr_departments,id'],
            'has_leader' => ['sometimes', 'nullable', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'string', 'in:name,code,team_type,created_at'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $query = WorkTeam::with(['department', 'teamLeader'])
            ->withCount(['activeMembers as members_count'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['team_type'] ?? null, fn ($query, $teamType) => $query->where('team_type', $teamType))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when(array_key_exists('has_leader', $filters) && $filters['has_leader'] !== null, function ($query) use ($filters) {
                return $filters['has_leader']
                    ? $query->whereNotNull('team_leader_id')
                    : $query->whereNull('team_leader_id');
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortField = $sortBy === 'name' ? 'name->'.app()->getLocale() : $sortBy;
        $query->orderBy($sortField, $filters['sort_order'] ?? 'asc');
        $page = $query->paginate($filters['per_page'] ?? 15);

        $allTeams = WorkTeam::query();
        $stats = [
            'total_count' => (clone $allTeams)->count(),
            'active_count' => (clone $allTeams)->where('is_active', true)->count(),
            'with_leader_count' => (clone $allTeams)->whereNotNull('team_leader_id')->count(),
            'total_members_count' => WorkTeamMember::whereNull('left_at')->whereHas('team')->count(),
        ];
        $teamTypes = WorkTeam::query()
            ->whereNotNull('team_type')
            ->distinct()
            ->orderBy('team_type')
            ->pluck('team_type')
            ->values();

        return response()->json([
            'data' => WorkTeamResource::collection($page->getCollection())->resolve(),
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'stats' => $stats,
            'team_types' => $teamTypes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        if ($request->filled('leader_id') && ! $request->filled('team_leader_id')) {
            $request->merge(['team_leader_id' => $request->input('leader_id')]);
        }

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_work_teams,code'],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'team_leader_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'team_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150), TranslatableFields::rules('description')));

        $team = WorkTeam::create($data);

        return response()->json([
            'message' => 'Work team created successfully.',
            'data' => new WorkTeamResource($team->load(['department', 'teamLeader', 'activeMembers'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $team = WorkTeam::with(['department', 'teamLeader', 'activeMembers.employee.position'])->findOrFail($id);

        return response()->json([
            'data' => new WorkTeamResource($team),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $team = WorkTeam::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        if ($request->filled('leader_id') && ! $request->filled('team_leader_id')) {
            $request->merge(['team_leader_id' => $request->input('leader_id')]);
        }

        $data = $request->validate(array_merge([
            'code' => ['sometimes', 'required', 'string', 'max:50', "unique:hr_work_teams,code,{$id}"],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'team_leader_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'team_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', false, 150), TranslatableFields::rules('description')));

        $team->update($data);

        return response()->json([
            'message' => 'Work team updated successfully.',
            'data' => new WorkTeamResource($team->load(['department', 'teamLeader', 'activeMembers'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $team = WorkTeam::findOrFail($id);
        $team->delete();

        return response()->json([
            'message' => 'Work team deleted successfully.',
        ]);
    }

    public function addMember(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $team = WorkTeam::findOrFail($id);

        $request->validate([
            'employee_id' => ['ulid', 'required', 'exists:hr_employees,id'],
            'role_in_team' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $employeeId = $request->input('employee_id');

        DB::transaction(function () use ($team, $employeeId, $request) {
            // End active membership if exists
            WorkTeamMember::where('team_id', $team->id)
                ->where('employee_id', $employeeId)
                ->whereNull('left_at')
                ->update(['left_at' => now()->toDateString()]);

            WorkTeamMember::create([
                'team_id' => $team->id,
                'employee_id' => $employeeId,
                'role_in_team' => $request->input('role_in_team', 'Member'),
                'is_primary' => $request->boolean('is_primary', true),
                'joined_at' => now()->toDateString(),
            ]);

            if ($request->boolean('is_primary', true)) {
                Employee::where('id', $employeeId)->update(['current_team_id' => $team->id]);
            }
        });

        return response()->json([
            'message' => 'Member added to team.',
            'data' => new WorkTeamResource($team->load(['department', 'teamLeader', 'activeMembers.employee.position'])),
        ]);
    }

    public function removeMember(string $id, string $memberId): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $team = WorkTeam::findOrFail($id);
        $member = WorkTeamMember::where('team_id', $team->id)->findOrFail($memberId);

        $member->update(['left_at' => now()->toDateString()]);

        // If employee's current_team_id is this team, clear it
        Employee::where('id', $member->employee_id)
            ->where('current_team_id', $team->id)
            ->update(['current_team_id' => null]);

        return response()->json([
            'message' => 'Member removed from team.',
        ]);
    }
}
