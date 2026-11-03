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
        $this->authorizeHrMutation('hr.certifications.manage');
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
        $this->authorizeHrMutation('hr.certifications.manage');
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
        $this->authorizeHrMutation('hr.certifications.manage');
        $skill = Skill::findOrFail($id);
        $skill->delete();

        return response()->json([
            'message' => 'Skill deleted successfully.',
        ]);
    }

    public function assignToEmployee(Request $request, string $employeeId): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
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

    public function verifyEmployeeSkill(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
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
}
