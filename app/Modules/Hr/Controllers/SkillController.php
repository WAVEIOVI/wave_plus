<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\Skill;
use App\Modules\Hr\Resources\SkillResource;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSkillRead();
        $query = Skill::withCount('employeeSkills');

        if ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $skills = $query->orderBy('name->'.app()->getLocale())->get();

        return response()->json([
            'data' => SkillResource::collection($skills)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSkillMutation();
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_skills,code'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150), TranslatableFields::rules('description')));

        $skill = Skill::create($data);

        return response()->json([
            'message' => 'Skill created successfully.',
            'data' => new SkillResource($skill),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeSkillMutation();
        $skill = Skill::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['sometimes', 'required', 'string', 'max:50', "unique:hr_skills,code,{$id}"],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', false, 150, true), TranslatableFields::rules('description')));

        $skill->update($data);

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data' => new SkillResource($skill),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeSkillMutation();
        $skill = Skill::findOrFail($id);
        $skill->delete();

        return response()->json([
            'message' => 'Skill deleted successfully.',
        ]);
    }

    public function assignToEmployee(Request $request, string $employeeId): JsonResponse
    {
        $this->authorizeSkillMutation();
        $employee = Employee::findOrFail($employeeId);

        $data = $request->validate([
            'skill_id' => ['ulid', 'required', 'exists:hr_skills,id'],
            'level' => ['required', 'in:basic,intermediate,advanced,expert'],
            'acquired_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $empSkill = EmployeeSkill::updateOrCreate(
            ['employee_id' => $employee->id, 'skill_id' => $data['skill_id']],
            [
                'level' => $data['level'],
                'acquired_date' => $data['acquired_date'] ?? now()->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Skill assigned to employee.',
            'data' => $empSkill->load('skill'),
        ]);
    }

    public function updateEmployeeSkill(Request $request, string $id): JsonResponse
    {
        $this->authorizeSkillMutation();
        $empSkill = EmployeeSkill::findOrFail($id);

        $data = $request->validate([
            'level' => ['sometimes', 'required', 'in:basic,intermediate,advanced,expert'],
            'acquired_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $empSkill->update(array_filter($data, fn ($val) => $val !== null));

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data' => $empSkill->load('skill'),
        ]);
    }

    public function destroyEmployeeSkill(string $id): JsonResponse
    {
        $this->authorizeSkillMutation();
        $empSkill = EmployeeSkill::findOrFail($id);
        $empSkill->delete();

        return response()->json([
            'message' => 'Skill removed from employee.',
        ]);
    }

    public function verifyEmployeeSkill(Request $request, string $id): JsonResponse
    {
        $this->authorizeSkillMutation();
        $empSkill = EmployeeSkill::findOrFail($id);

        $empSkill->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $request->input('notes', $empSkill->notes),
        ]);

        return response()->json([
            'message' => 'Skill verified successfully.',
            'data' => $empSkill->load(['skill', 'verifier']),
        ]);
    }

    public function employeeDevelopmentSummary(string $employeeId): JsonResponse
    {
        $employee = Employee::with([
            'employeeSkills.skill',
            'certifications.certification',
            'trainings.session.program',
        ])->findOrFail($employeeId);

        $user = request()->user();
        abort_unless($user, 403);
        $isSelf = $user->id === $employee->user_id;

        if (! $isSelf && ! $user->hasRole(['Super Admin', 'Admin', 'HR Manager', 'HR Officer'])) {
            abort_unless(
                $user->can('hr.skills.view')
                    || $user->can('hr.certifications.view')
                    || $user->can('hr.training.view')
                    || $user->can('hr.employees.view'),
                403,
                'You do not have permission to view this employee summary.'
            );
        }

        $skills = $employee->employeeSkills;
        $certs = $employee->certifications;
        $trainings = $employee->trainings;

        $levelCounts = [
            'basic' => $skills->where('level', 'basic')->count(),
            'intermediate' => $skills->where('level', 'intermediate')->count(),
            'advanced' => $skills->where('level', 'advanced')->count(),
            'expert' => $skills->where('level', 'expert')->count(),
        ];

        $certCounts = [
            'total' => $certs->count(),
            'valid' => $certs->where('computed_status', 'valid')->count(),
            'expiring' => $certs->where('computed_status', 'expiring')->count(),
            'expired' => $certs->where('computed_status', 'expired')->count(),
            'pending_verification' => $certs->where('computed_status', 'pending_verification')->count(),
        ];

        $trainingCounts = [
            'total' => $trainings->count(),
            'completed' => $trainings->where('status', 'completed')->count(),
            'enrolled' => $trainings->where('status', 'enrolled')->count(),
            'in_progress' => $trainings->where('status', 'in_progress')->count(),
            'average_score' => round((float) $trainings->where('status', 'completed')->whereNotNull('score')->avg('score'), 1),
        ];

        $expiringCerts = $certs->filter(fn ($c) => $c->computed_status === 'expiring' || ($c->expiry_date && now()->diffInDays($c->expiry_date, false) <= 60 && ! $c->expiry_date->isPast()))
            ->map(fn ($c) => [
                'id' => $c->id,
                'certification_id' => $c->certification_id,
                'name' => $c->certification?->getTranslations('name') ?? [],
                'code' => $c->certification?->code,
                'certificate_number' => $c->certificate_number,
                'expiry_date' => $c->expiry_date?->toDateString(),
                'days_remaining' => (int) now()->diffInDays($c->expiry_date, false),
            ])->values();

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'skills' => [
                    'total' => $skills->count(),
                    'verified' => $skills->where('is_verified', true)->count(),
                    'by_level' => $levelCounts,
                ],
                'certifications' => $certCounts,
                'trainings' => $trainingCounts,
                'expiring_certifications' => $expiringCerts,
            ],
        ]);
    }

    protected function authorizeSkillRead(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager', 'HR Officer'])) {
            return;
        }
        abort_unless(
            $user->can('hr.skills.view')
                || $user->can('hr.skills.manage')
                || $user->can('hr.certifications.view')
                || $user->can('hr.employees.view')
                || $user->can('read-PeopleSkills'),
            403,
            'You do not have permission to view skills.'
        );
    }

    protected function authorizeSkillMutation(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }
        abort_unless(
            $user->can('hr.skills.manage') || $user->can('hr.certifications.manage'),
            403,
            'You do not have permission to manage skills.'
        );
    }
}
