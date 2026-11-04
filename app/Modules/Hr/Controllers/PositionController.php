<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Resources\PositionResource;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePositionRead();

        $filters = $request->validate([
            'department_id' => ['sometimes', 'nullable', 'ulid', 'exists:hr_departments,id'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'string', 'in:title,created_at'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $query = Position::with('department')->withCount('employees');

        $query
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search) {
                $query->where('title->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'title';
        $sortField = $sortBy === 'title' ? 'title->'.app()->getLocale() : $sortBy;
        $query->orderBy($sortField, $filters['sort_order'] ?? 'asc');

        if (isset($filters['page']) || isset($filters['per_page'])) {
            $allPositions = Position::query();
            $stats = [
                'total_count' => (clone $allPositions)->count(),
                'active_count' => (clone $allPositions)->where('status', 'active')->count(),
                'with_rate_count' => (clone $allPositions)->whereNotNull('standard_hourly_rate')->count(),
                'employees_count' => Employee::whereIn('position_id', (clone $allPositions)->select('id'))->count(),
            ];
            $page = $query->paginate($filters['per_page'] ?? 15);

            return response()->json([
                'data' => PositionResource::collection($page->getCollection())->resolve(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'stats' => $stats,
            ]);
        }

        $positions = $query->get();

        return response()->json([
            'data' => PositionResource::collection($positions)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePositionMutation();
        TranslatableFields::normalizeRequest($request, ['title', 'description', 'requirements']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_positions,code'],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'standard_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ], TranslatableFields::rules('title', true, 150), TranslatableFields::rules('description'), TranslatableFields::rules('requirements')));

        $position = Position::create($data);

        return response()->json([
            'message' => 'Position created successfully.',
            'data' => new PositionResource($position->load('department')),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizePositionRead();
        $position = Position::with(['department', 'employees'])->findOrFail($id);

        return response()->json([
            'data' => new PositionResource($position),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePositionMutation();
        $position = Position::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['title', 'description', 'requirements']);

        $data = $request->validate(array_merge([
            'code' => ['sometimes', 'required', 'string', 'max:50', "unique:hr_positions,code,{$id}"],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'standard_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ], TranslatableFields::rules('title', false, 150), TranslatableFields::rules('description'), TranslatableFields::rules('requirements')));

        $position->update($data);

        return response()->json([
            'message' => 'Position updated successfully.',
            'data' => new PositionResource($position->load('department')),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizePositionMutation();
        $position = Position::withCount('employees')->findOrFail($id);

        if ($position->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete position with assigned employees. Please reassign them first.',
            ], 422);
        }

        $position->delete();

        return response()->json([
            'message' => 'Position deleted successfully.',
        ]);
    }

    protected function authorizePositionRead(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager', 'HR Officer'])) {
            return;
        }
        abort_unless(
            $user->can('hr.positions.view')
                || $user->can('hr.positions.manage')
                || $user->can('hr.employees.view')
                || $user->can('read-PeopleSkills'),
            403,
            'You do not have permission to view positions.'
        );
    }

    protected function authorizePositionMutation(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }
        abort_unless(
            $user->can('hr.positions.manage') || $user->can('hr.employees.edit'),
            403,
            'You do not have permission to manage positions.'
        );
    }
}
