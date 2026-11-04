<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\EmployeeTraining;
use App\Modules\Hr\Models\TrainingProgram;
use App\Modules\Hr\Models\TrainingSession;
use App\Modules\Hr\Resources\TrainingProgramResource;
use App\Modules\Hr\Resources\TrainingSessionResource;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainingController extends Controller
{
    use AuthorizesHrMutations;

    public function programs(): JsonResponse
    {
        $this->authorizeHrRead('hr.training.view');
        $programs = TrainingProgram::with(['targetSkill', 'targetCertification'])
            ->withCount('sessions')
            ->orderBy('name->'.app()->getLocale())
            ->get();

        return response()->json([
            'data' => TrainingProgramResource::collection($programs)->resolve(),
        ]);
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');

        if ($request->filled('title') && ! $request->filled('name')) {
            $request->merge(['name' => $request->input('title')]);
        }

        TranslatableFields::normalizeRequest($request, ['name', 'description', 'provider']);

        if (! $request->filled('code') && $request->filled('name')) {
            $translations = $request->input('name');
            $programName = $translations[app()->getLocale()] ?? reset($translations);
            $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $programName), 0, 24));
            $request->merge(['code' => 'TR-'.$slug.'-'.now()->format('ymd')]);
        }

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_training_programs,code'],
            'category' => ['nullable', 'string', 'max:100'],
            'duration_hours' => ['required', 'numeric', 'min:1'],
            'target_skill_id' => ['ulid', 'nullable', 'exists:hr_skills,id'],
            'target_certification_id' => ['ulid', 'nullable', 'exists:hr_certifications,id'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150), TranslatableFields::rules('description'), TranslatableFields::rules('provider', false, 150)));

        $program = TrainingProgram::create($data);

        return response()->json([
            'message' => 'Training program created.',
            'data' => new TrainingProgramResource($program),
        ], 201);
    }

    public function sessions(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.training.view');
        $query = TrainingSession::with(['program.targetSkill', 'program.targetCertification', 'participants.employee']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sessions = $query->orderByDesc('start_date')->get();

        return response()->json([
            'data' => TrainingSessionResource::collection($sessions)->resolve(),
        ]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        TranslatableFields::normalizeRequest($request, ['trainer']);

        $data = $request->validate(array_merge([
            'training_program_id' => ['ulid', 'required', 'exists:hr_training_programs,id'],
            'session_code' => ['required', 'string', 'max:50', 'unique:hr_training_sessions,session_code'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:150'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
        ], TranslatableFields::rules('trainer', false, 150)));

        $session = TrainingSession::create($data);

        return response()->json([
            'message' => 'Training session scheduled.',
            'data' => new TrainingSessionResource($session->load('program')),
        ], 201);
    }

    public function enrollEmployee(Request $request, string $sessionId): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');

        $session = TrainingSession::findOrFail($sessionId);

        $request->validate([
            'employee_id' => ['ulid', 'required', 'exists:hr_employees,id'],
        ]);

        $employeeId = $request->input('employee_id');

        $participant = EmployeeTraining::firstOrCreate(
            ['training_session_id' => $session->id, 'employee_id' => $employeeId],
            ['status' => 'enrolled']
        );

        return response()->json([
            'message' => 'Employee enrolled in session.',
            'data' => $participant->load('employee'),
        ], 201);
    }

    public function completeTraining(Request $request, string $participantId): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $participant = EmployeeTraining::with('session.program.targetCertification')->findOrFail($participantId);

        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:completed,failed,attended'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($participant, $data) {
            $participant->update([
                'status' => $data['status'],
                'score' => $data['score'] ?? null,
                'certificate_number' => $data['certificate_number'] ?? null,
                'completion_date' => now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            // If completed, automatically update employee skill and/or certification without duplicates
            if ($data['status'] === 'completed') {
                $this->syncParticipantQualifications($participant, $data['certificate_number'] ?? null);
            }
        });

        return response()->json([
            'message' => 'Training record updated and qualifications synchronized.',
            'data' => $participant->fresh(),
        ]);
    }

    public function batchAttendance(Request $request, string $sessionId): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $session = TrainingSession::with('program.targetCertification')->findOrFail($sessionId);

        $data = $request->validate([
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.id' => ['required', 'ulid', 'exists:hr_employee_trainings,id'],
            'participants.*.status' => ['required', 'in:enrolled,attended,completed,failed,cancelled'],
            'participants.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'participants.*.certificate_number' => ['nullable', 'string', 'max:100'],
            'participants.*.notes' => ['nullable', 'string'],
        ]);

        $updated = [];

        DB::transaction(function () use ($data, $session, &$updated) {
            foreach ($data['participants'] as $pData) {
                $participant = EmployeeTraining::where('training_session_id', $session->id)
                    ->where('id', $pData['id'])
                    ->firstOrFail();

                $updateFields = [
                    'status' => $pData['status'],
                    'notes' => $pData['notes'] ?? $participant->notes,
                ];

                if (array_key_exists('score', $pData)) {
                    $updateFields['score'] = $pData['score'];
                }
                if (! empty($pData['certificate_number'])) {
                    $updateFields['certificate_number'] = $pData['certificate_number'];
                }
                if (in_array($pData['status'], ['completed', 'attended'])) {
                    $updateFields['completion_date'] = now()->toDateString();
                }

                $participant->update($updateFields);

                if ($pData['status'] === 'completed') {
                    $this->syncParticipantQualifications($participant, $pData['certificate_number'] ?? null);
                }

                $updated[] = $participant->fresh(['employee']);
            }
        });

        return response()->json([
            'message' => 'Batch attendance and completion recorded successfully.',
            'data' => $updated,
        ]);
    }

    protected function syncParticipantQualifications(EmployeeTraining $participant, ?string $certificateNumber = null): void
    {
        $program = $participant->session?->program;
        if (! $program) {
            return;
        }

        if ($program->target_skill_id) {
            $existingSkill = EmployeeSkill::where('employee_id', $participant->employee_id)
                ->where('skill_id', $program->target_skill_id)
                ->first();

            $ranks = ['basic' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4];
            $targetLevel = 'intermediate';
            if ($existingSkill && ($ranks[$existingSkill->level] ?? 0) > ($ranks[$targetLevel] ?? 2)) {
                $targetLevel = $existingSkill->level;
            }

            EmployeeSkill::updateOrCreate(
                ['employee_id' => $participant->employee_id, 'skill_id' => $program->target_skill_id],
                [
                    'level' => $targetLevel,
                    'acquired_date' => now()->toDateString(),
                    'is_verified' => true,
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                    'notes' => "Awarded via training completion: {$program->name}",
                ]
            );
        }

        if ($program->target_certification_id) {
            $existingCert = EmployeeCertification::where('employee_id', $participant->employee_id)
                ->where('certification_id', $program->target_certification_id)
                ->first();

            $validityMonths = $program->targetCertification?->validity_period_months ?? 24;
            $newExpiry = now()->addMonths($validityMonths)->toDateString();

            if ($existingCert) {
                $existingCert->update([
                    'issue_date' => now()->toDateString(),
                    'expiry_date' => $newExpiry,
                    'certificate_number' => $certificateNumber ?? $existingCert->certificate_number ?? ('CERT-'.strtoupper(uniqid())),
                    'status' => 'valid',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                    'notes' => "Renewed via training program: {$program->name}",
                ]);
            } else {
                EmployeeCertification::create([
                    'employee_id' => $participant->employee_id,
                    'certification_id' => $program->target_certification_id,
                    'certificate_number' => $certificateNumber ?? ('CERT-'.strtoupper(uniqid())),
                    'issue_date' => now()->toDateString(),
                    'expiry_date' => $newExpiry,
                    'status' => 'valid',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                    'notes' => "Awarded via training program: {$program->name}",
                ]);
            }
        }
    }

    public function showProgram(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.training.view');
        $program = TrainingProgram::with(['targetSkill', 'targetCertification', 'sessions'])
            ->findOrFail($id);

        return response()->json([
            'data' => new TrainingProgramResource($program),
        ]);
    }

    public function updateProgram(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $program = TrainingProgram::findOrFail($id);

        if ($request->filled('title') && ! $request->filled('name')) {
            $request->merge(['name' => $request->input('title')]);
        }

        TranslatableFields::normalizeRequest($request, ['name', 'description', 'provider']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_training_programs,code,'.$id],
            'category' => ['nullable', 'string', 'max:100'],
            'duration_hours' => ['required', 'numeric', 'min:1'],
            'target_skill_id' => ['ulid', 'nullable', 'exists:hr_skills,id'],
            'target_certification_id' => ['ulid', 'nullable', 'exists:hr_certifications,id'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150), TranslatableFields::rules('description'), TranslatableFields::rules('provider', false, 150)));

        $program->update($data);

        return response()->json([
            'message' => 'Training program updated.',
            'data' => new TrainingProgramResource($program->fresh(['targetSkill', 'targetCertification'])),
        ]);
    }

    public function destroyProgram(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $program = TrainingProgram::findOrFail($id);
        $program->delete();

        return response()->json([
            'message' => 'Training program deleted.',
        ]);
    }

    public function showSession(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.training.view');
        $session = TrainingSession::with(['program.targetSkill', 'program.targetCertification', 'participants.employee'])
            ->findOrFail($id);

        return response()->json([
            'data' => new TrainingSessionResource($session),
        ]);
    }

    public function updateSession(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $session = TrainingSession::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['trainer']);

        $data = $request->validate(array_merge([
            'training_program_id' => ['ulid', 'required', 'exists:hr_training_programs,id'],
            'session_code' => ['required', 'string', 'max:50', 'unique:hr_training_sessions,session_code,'.$id],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:150'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
        ], TranslatableFields::rules('trainer', false, 150)));

        $session->update($data);

        return response()->json([
            'message' => 'Training session updated.',
            'data' => new TrainingSessionResource($session->fresh(['program'])),
        ]);
    }

    public function destroySession(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $session = TrainingSession::findOrFail($id);
        $session->delete();

        return response()->json([
            'message' => 'Training session deleted.',
        ]);
    }

    public function destroyParticipant(string $participantId): JsonResponse
    {
        $this->authorizeHrMutation('hr.training.manage');
        $participant = EmployeeTraining::findOrFail($participantId);
        $participant->delete();

        return response()->json([
            'message' => 'Participant removed from session.',
        ]);
    }
}
