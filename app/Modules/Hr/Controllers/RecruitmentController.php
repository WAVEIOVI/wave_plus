<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\HrAuditLog;
use App\Modules\Hr\Models\Interview;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Requests\HireCandidateRequest;
use App\Modules\Hr\Resources\CandidateResource;
use App\Modules\Hr\Resources\EmployeeResource;
use App\Modules\Hr\Services\CandidateHiringService;
use App\Modules\Shared\Services\MediaValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecruitmentController extends Controller
{
    use AuthorizesHrMutations, AuthorizesRequests;

    public function __construct(
        protected CandidateHiringService $hiringService
    ) {}

    public function jobOpenings(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.recruitment.view');
        $query = JobOpening::with(['department', 'position'])->withCount('applications');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $openings = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $openings,
        ]);
    }

    public function storeJobOpening(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'position_id' => ['ulid', 'nullable', 'exists:hr_positions,id'],
            'openings_count' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,closed,cancelled'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $opening = JobOpening::create($data);

        return response()->json([
            'message' => 'Job opening created.',
            'data' => $opening->load(['department', 'position']),
        ], 201);
    }

    public function candidates(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.recruitment.view');
        $query = Candidate::with(['applications.jobOpening', 'interviews.interviewer']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($openingId = $request->input('job_opening_id') ?: $request->input('opening_id')) {
            $query->whereHas('applications', function ($q) use ($openingId) {
                $q->where('job_opening_id', $openingId);
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $candidates = $query->orderByDesc('created_at')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => CandidateResource::collection($candidates->getCollection())->resolve(),
            'meta' => [
                'current_page' => $candidates->currentPage(),
                'per_page' => $candidates->perPage(),
                'total' => $candidates->total(),
                'last_page' => $candidates->lastPage(),
            ],
        ]);
    }

    public function storeCandidate(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'job_opening_id' => ['ulid', 'nullable', 'exists:hr_job_openings,id'],
        ]);

        $candidate = Candidate::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'applied',
        ]);

        if ($request->hasFile('resume')) {
            $fileMeta = MediaValidationService::validateAndProcess(
                $request->file('resume'),
                MediaValidationService::CATEGORY_RESUME,
                'resume'
            );
            $candidate->addMediaFromRequest('resume')
                ->usingFileName($fileMeta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $fileMeta['uploaded_by'],
                    'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                ])
                ->toMediaCollection('resume');
        }

        if (! empty($data['job_opening_id'])) {
            $candidate->applications()->create([
                'job_opening_id' => $data['job_opening_id'],
                'application_date' => now()->toDateString(),
                'status' => 'applied',
            ]);
        }

        return response()->json([
            'message' => 'Candidate registered.',
            'data' => new CandidateResource($candidate),
        ], 201);
    }

    public function scheduleInterview(Request $request, string $candidateId): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $candidate = Candidate::findOrFail($candidateId);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:180'],
            'job_opening_id' => ['ulid', 'nullable', 'exists:hr_job_openings,id'],
            'interviewer_id' => ['ulid', 'nullable', 'exists:users,id'],
            'feedback' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $interview = Interview::create([
            'candidate_id' => $candidate->id,
            'job_opening_id' => $data['job_opening_id'] ?? null,
            'interviewer_id' => $data['interviewer_id'] ?? auth()->id(),
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 45,
            'feedback' => $data['feedback'] ?? null,
            'rating' => $data['rating'] ?? null,
            'status' => 'scheduled',
        ]);

        $candidate->update(['status' => 'interview']);

        return response()->json([
            'message' => 'Interview scheduled successfully.',
            'data' => $interview,
        ], 201);
    }

    public function updateStage(Request $request, string $candidateId): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $candidate = Candidate::findOrFail($candidateId);

        $data = $request->validate([
            'status' => ['required', 'in:applied,screening,interview,evaluated,selected,offer,hired,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $previousStatus = $candidate->status;
        $candidate->update([
            'status' => $data['status'],
            'notes' => ! empty($data['notes'])
                ? trim(($candidate->notes ? $candidate->notes."\n" : '').$data['notes'])
                : $candidate->notes,
        ]);

        HrAuditLog::create([
            'auditable_type' => Candidate::class,
            'auditable_id' => $candidate->id,
            'actor_id' => auth()->id(),
            'action' => 'STAGE_CHANGE',
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => $candidate->status],
            'reason' => $data['notes'] ?? "Stage transitioned from {$previousStatus} to {$candidate->status}.",
        ]);

        return response()->json([
            'message' => 'Candidate status updated.',
            'status' => $candidate->status,
            'data' => new CandidateResource($candidate->fresh(['applications.jobOpening', 'interviews.interviewer'])),
        ]);
    }

    public function updateInterview(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $interview = Interview::findOrFail($id);

        $data = $request->validate([
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:180'],
            'interviewer_id' => ['nullable', 'ulid', 'exists:users,id'],
            'feedback' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);

        $interview->update($data);

        if (($data['status'] ?? null) === 'completed' && $interview->candidate && $interview->candidate->status === 'interview') {
            $interview->candidate->update(['status' => 'evaluated']);
        }

        HrAuditLog::create([
            'auditable_type' => Interview::class,
            'auditable_id' => $interview->id,
            'actor_id' => auth()->id(),
            'action' => 'INTERVIEW_EVALUATION',
            'new_values' => [
                'rating' => $interview->rating,
                'status' => $interview->status,
                'candidate_id' => $interview->candidate_id,
            ],
            'reason' => 'Interview evaluation updated.',
        ]);

        return response()->json([
            'message' => 'Interview evaluation updated successfully.',
            'data' => $interview->fresh(['interviewer', 'jobOpening', 'candidate']),
        ]);
    }

    public function hireCandidate(HireCandidateRequest $request, string $candidateId): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.hire');
        $candidate = Candidate::findOrFail($candidateId);

        if ($candidate->status === 'hired') {
            return response()->json([
                'message' => "Candidate {$candidate->full_name} is already hired.",
                'errors' => [
                    'candidate' => ["Candidate {$candidate->full_name} is already hired."],
                ],
            ], 422);
        }

        try {
            $employee = $this->hiringService->hireCandidate(
                candidate: $candidate,
                departmentId: $request->input('department_id'),
                positionId: $request->input('position_id'),
                hireDate: Carbon::parse($request->input('hire_date')),
                managerId: $request->input('manager_id'),
                workEmail: $request->input('work_email'),
                workPhone: $request->input('work_phone'),
                employmentType: $request->input('employment_type', 'full_time'),
                contractType: $request->input('contract_type', 'permanent'),
                actorId: auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'candidate' => [$e->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'message' => "Candidate {$candidate->full_name} successfully hired as employee {$employee->employee_number}.",
            'employee' => new EmployeeResource($employee->load(['department', 'position'])),
        ], 201);
    }

    public function showJobOpening(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.recruitment.view');
        $opening = JobOpening::with(['department', 'position', 'applications.candidate'])->findOrFail($id);

        return response()->json([
            'data' => $opening,
        ]);
    }

    public function updateJobOpening(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $opening = JobOpening::findOrFail($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'position_id' => ['ulid', 'nullable', 'exists:hr_positions,id'],
            'openings_count' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,closed,cancelled'],
            'published_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
        ]);

        if (($data['status'] ?? null) === 'published' && empty($opening->published_at) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if (($data['status'] ?? null) === 'closed' && empty($opening->closed_at) && empty($data['closed_at'])) {
            $data['closed_at'] = now();
        }

        $opening->update($data);

        return response()->json([
            'message' => 'Job opening updated.',
            'data' => $opening->fresh(['department', 'position']),
        ]);
    }

    public function destroyJobOpening(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $opening = JobOpening::findOrFail($id);
        $opening->delete();

        return response()->json([
            'message' => 'Job opening deleted.',
        ]);
    }

    public function showCandidate(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.recruitment.view');
        $candidate = Candidate::with(['applications.jobOpening', 'interviews.interviewer'])->findOrFail($id);

        return response()->json([
            'data' => new CandidateResource($candidate),
        ]);
    }

    public function updateCandidate(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $candidate = Candidate::findOrFail($id);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:applied,screening,interview,evaluated,selected,offer,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $candidate->update($data);

        if ($request->hasFile('resume')) {
            $fileMeta = MediaValidationService::validateAndProcess(
                $request->file('resume'),
                MediaValidationService::CATEGORY_RESUME,
                'resume'
            );
            $candidate->clearMediaCollection('resume');
            $candidate->addMediaFromRequest('resume')
                ->usingFileName($fileMeta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $fileMeta['uploaded_by'],
                    'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                ])
                ->toMediaCollection('resume');
        }

        return response()->json([
            'message' => 'Candidate updated.',
            'data' => new CandidateResource($candidate->fresh(['applications.jobOpening', 'interviews.interviewer'])),
        ]);
    }

    public function downloadResume(string $id, ?string $mediaId = null)
    {
        $this->authorizeHrRead('hr.recruitment.view');
        $candidate = Candidate::findOrFail($id);

        $media = $mediaId
            ? $candidate->media()->findOrFail($mediaId)
            : $candidate->getFirstMedia('resume');

        if (! $media) {
            abort(404, 'No résumé attached to this candidate.');
        }

        return $media->toResponse(request());
    }

    public function storeMedia(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $candidate = Candidate::findOrFail($id);

        $request->validate([
            'file' => ['required', 'file'],
            'collection' => ['nullable', 'in:resume,documents'],
        ]);

        $collection = $request->input('collection', 'documents');
        $category = $collection === 'resume'
            ? MediaValidationService::CATEGORY_RESUME
            : MediaValidationService::CATEGORY_DOCUMENT;

        $fileMeta = MediaValidationService::validateAndProcess(
            $request->file('file'),
            $category,
            'file'
        );

        if ($collection === 'resume') {
            $candidate->clearMediaCollection('resume');
        }

        $media = $candidate->addMediaFromRequest('file')
            ->usingFileName($fileMeta['original_name'])
            ->withCustomProperties([
                'uploaded_by' => $fileMeta['uploaded_by'],
                'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            ])
            ->toMediaCollection($collection);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'media' => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                'collection' => $media->collection_name,
                'url' => url("/api/hr/recruitment/candidates/{$candidate->id}/media/{$media->id}/download"),
                'download_url' => url("/api/hr/recruitment/candidates/{$candidate->id}/media/{$media->id}/download"),
                'is_private' => true,
            ],
        ], 201);
    }

    public function destroyMedia(string $id, string $mediaId): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $candidate = Candidate::findOrFail($id);
        $media = $candidate->media()->findOrFail($mediaId);
        $media->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    public function destroyCandidate(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.recruitment.manage');
        $candidate = Candidate::findOrFail($id);
        $candidate->delete();

        return response()->json([
            'message' => 'Candidate deleted.',
        ]);
    }
}
