<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Resources\DepartmentResource;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeDepartmentRead();

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'parent_department_id' => ['sometimes', 'nullable', 'ulid', 'exists:hr_departments,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'string', 'in:name,sort_order,created_at'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $paginated = isset($filters['page']) || isset($filters['per_page']);
        $departments = $paginated
            ? null
            : Department::with(['parent', 'manager', 'children', 'positions'])
                ->withCount(['employees', 'positions', 'workTeams'])
                ->whereNull('parent_department_id')
                ->orderBy('sort_order')
                ->get();

        $allFlatQuery = Department::with(['parent', 'manager', 'positions'])
            ->withCount(['employees', 'positions', 'workTeams'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['parent_department_id'] ?? null, fn ($query, $parentId) => $query->where('parent_department_id', $parentId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortField = $sortBy === 'name' ? 'name->'.app()->getLocale() : $sortBy;
        $allFlatQuery->orderBy($sortField, $filters['sort_order'] ?? 'asc');

        if ($paginated) {
            $allDepartments = Department::query();
            $stats = [
                'total_count' => (clone $allDepartments)->count(),
                'active_count' => (clone $allDepartments)->where('status', 'active')->count(),
                'with_manager_count' => (clone $allDepartments)->whereNotNull('manager_id')->count(),
                'positions_count' => Position::count(),
            ];
            $page = $allFlatQuery->paginate($filters['per_page'] ?? 15);

            return response()->json([
                'data' => DepartmentResource::collection($page->getCollection())->resolve(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'stats' => $stats,
            ]);
        }

        $allFlat = $allFlatQuery->get();

        return response()->json([
            'tree' => DepartmentResource::collection($departments)->resolve(),
            'data' => DepartmentResource::collection($allFlat)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDepartmentMutation();
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_departments,code'],
            'parent_department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'manager_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer'],
            'position_ids' => ['nullable', 'array'],
            'position_ids.*' => ['ulid', 'exists:hr_positions,id'],
        ], TranslatableFields::rules('name', true, 150), TranslatableFields::rules('description')));

        $positionIds = $data['position_ids'] ?? [];
        unset($data['position_ids']);

        $department = Department::create($data);

        if (! empty($positionIds)) {
            \App\Modules\Hr\Models\Position::whereIn('id', $positionIds)->update(['department_id' => $department->id]);
        }

        return response()->json([
            'message' => 'Department created successfully.',
            'data' => new DepartmentResource($department->load(['parent', 'manager', 'positions'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeDepartmentRead();

        $department = Department::with([
            'parent',
            'manager',
            'children',
            'positions',
            'employees',
            'workTeams.teamLeader',
        ])->withCount(['employees', 'positions', 'workTeams'])->findOrFail($id);

        return response()->json([
            'data' => array_merge((new DepartmentResource($department))->resolve(), [
                'employees' => $department->employees->map(fn ($employee) => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                ])->values(),
                'work_teams' => $department->workTeams->map(fn ($team) => [
                    'id' => $team->id,
                    'code' => $team->code,
                    'name' => $team->getTranslations('name'),
                    'team_leader_name' => $team->teamLeader?->full_name,
                ])->values(),
            ]),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeDepartmentMutation();
        $department = Department::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['sometimes', 'required', 'string', 'max:50', "unique:hr_departments,code,{$id}"],
            'parent_department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'manager_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer'],
            'position_ids' => ['nullable', 'array'],
            'position_ids.*' => ['ulid', 'exists:hr_positions,id'],
        ], TranslatableFields::rules('name', false, 150), TranslatableFields::rules('description')));

        if (array_key_exists('position_ids', $data)) {
            $positionIds = $data['position_ids'] ?? [];
            unset($data['position_ids']);

            // Unset department for positions previously assigned to this dept that are not in new list
            \App\Modules\Hr\Models\Position::where('department_id', $department->id)
                ->whereNotIn('id', $positionIds)
                ->update(['department_id' => null]);

            if (! empty($positionIds)) {
                \App\Modules\Hr\Models\Position::whereIn('id', $positionIds)->update(['department_id' => $department->id]);
            }
        }

        $department->update($data);

        return response()->json([
            'message' => 'Department updated successfully.',
            'data' => new DepartmentResource($department->load(['parent', 'manager', 'positions'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeDepartmentMutation();
        $department = Department::withCount(['employees', 'children'])->findOrFail($id);

        if ($department->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete department with assigned employees. Please reassign them first.',
            ], 422);
        }

        if ($department->children_count > 0) {
            return response()->json([
                'message' => 'Cannot delete department that has sub-departments. Please reassign or delete sub-departments first.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }

    protected function authorizeDepartmentRead(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager', 'HR Officer'])) {
            return;
        }
        abort_unless(
            $user->can('hr.departments.view')
                || $user->can('hr.departments.manage')
                || $user->can('hr.employees.view')
                || $user->can('read-PeopleSkills'),
            403,
            'You do not have permission to view departments.'
        );
    }

    protected function authorizeDepartmentMutation(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }
        abort_unless(
            $user->can('hr.departments.manage') || $user->can('hr.employees.edit'),
            403,
            'You do not have permission to manage departments.'
        );
    }
}
