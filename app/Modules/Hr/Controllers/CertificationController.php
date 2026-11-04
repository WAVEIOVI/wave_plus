<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Certification;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Resources\CertificationResource;
use App\Modules\Hr\Support\TranslatableFields;
use App\Modules\Shared\Services\MediaValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.certifications.view');
        $query = Certification::withCount(['employeeCertifications as active_count' => fn ($q) => $q->where('status', 'valid')]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('issuing_authority->'.app()->getLocale(), 'like', "%{$search}%");
            });
        }

        $certs = $query->orderBy('name->'.app()->getLocale())->get();

        return response()->json([
            'data' => CertificationResource::collection($certs)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');

        if ($request->filled('validity_months') && ! $request->filled('validity_period_months')) {
            $request->merge(['validity_period_months' => $request->input('validity_months')]);
        }
        TranslatableFields::normalizeRequest($request, ['name', 'issuing_authority', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_certifications,code'],
            'validity_period_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150), TranslatableFields::rules('issuing_authority', false, 150), TranslatableFields::rules('description')));

        $cert = Certification::create($data);

        return response()->json([
            'message' => 'Certification created successfully.',
            'data' => new CertificationResource($cert),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
        $cert = Certification::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name', 'issuing_authority', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['sometimes', 'required', 'string', 'max:50', "unique:hr_certifications,code,{$id}"],
            'validity_period_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', false, 150, true), TranslatableFields::rules('issuing_authority', false, 150), TranslatableFields::rules('description')));

        $cert->update($data);

        return response()->json([
            'message' => 'Certification updated successfully.',
            'data' => new CertificationResource($cert),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
        $cert = Certification::findOrFail($id);
        $cert->delete();

        return response()->json([
            'message' => 'Certification deleted successfully.',
        ]);
    }

    public function expiringCertifications(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.certifications.view');
        $days = (int) $request->input('days', 60);
        $days = max(1, min(365, $days));

        $expiring = EmployeeCertification::with(['employee.department', 'employee.position', 'certification'])
            ->expiring($days)
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'data' => $expiring->map(fn ($c) => [
                'id' => $c->id,
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee?->full_name ?? '',
                'employee_number' => $c->employee?->employee_number ?? '',
                'department' => $c->employee?->department?->name ?? '',
                'position' => $c->employee?->position?->title ?? '',
                'certification_id' => $c->certification_id,
                'certification_name' => $c->certification?->getTranslations('name') ?? [],
                'code' => $c->certification?->code ?? '',
                'certificate_number' => $c->certificate_number,
                'issuing_organization' => $c->issuing_organization,
                'issue_date' => $c->issue_date?->toDateString(),
                'expiry_date' => $c->expiry_date?->toDateString(),
                'days_until_expiry' => $c->expiry_date ? (int) now()->diffInDays($c->expiry_date, false) : null,
                'status' => $c->computed_status,
            ]),
        ]);
    }

    public function assignToEmployee(Request $request, string $employeeId): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
        $employee = Employee::findOrFail($employeeId);

        $data = $request->validate([
            'certification_id' => ['ulid', 'required', 'exists:hr_certifications,id'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issuing_organization' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
            'status' => ['nullable', 'in:valid,expiring,expired,pending_verification'],
            'notes' => ['nullable', 'string'],
        ]);

        $existing = EmployeeCertification::where('employee_id', $employee->id)
            ->where('certification_id', $data['certification_id'])
            ->first();

        if ($existing) {
            $existing->update([
                'certificate_number' => $data['certificate_number'] ?? $existing->certificate_number,
                'issuing_organization' => $data['issuing_organization'] ?? $existing->issuing_organization,
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => $data['status'] ?? 'valid',
                'notes' => $data['notes'] ?? $existing->notes,
            ]);
            $empCert = $existing;
            $statusCode = 200;
        } else {
            $empCert = EmployeeCertification::create([
                'employee_id' => $employee->id,
                'certification_id' => $data['certification_id'],
                'certificate_number' => $data['certificate_number'] ?? null,
                'issuing_organization' => $data['issuing_organization'] ?? null,
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => $data['status'] ?? 'valid',
                'notes' => $data['notes'] ?? null,
            ]);
            $statusCode = 201;
        }

        if ($request->hasFile('file')) {
            $fileMeta = MediaValidationService::validateAndProcess(
                $request->file('file'),
                MediaValidationService::CATEGORY_CERTIFICATE,
                'file'
            );
            $empCert->addMediaFromRequest('file')
                ->usingFileName($fileMeta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $fileMeta['uploaded_by'],
                    'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                ])
                ->toMediaCollection('certificate_files');
        }

        return response()->json([
            'message' => 'Certification assigned to employee.',
            'data' => $empCert->load('certification'),
        ], $statusCode);
    }

    public function updateEmployeeCertification(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
        $empCert = EmployeeCertification::findOrFail($id);

        $data = $request->validate([
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issuing_organization' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['sometimes', 'required', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:valid,expiring,expired,pending_verification'],
            'notes' => ['nullable', 'string'],
        ]);

        $empCert->update(array_filter($data, fn ($val) => $val !== null));

        if ($request->hasFile('file')) {
            $fileMeta = MediaValidationService::validateAndProcess(
                $request->file('file'),
                MediaValidationService::CATEGORY_CERTIFICATE,
                'file'
            );
            $empCert->addMediaFromRequest('file')
                ->usingFileName($fileMeta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $fileMeta['uploaded_by'],
                    'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                ])
                ->toMediaCollection('certificate_files');
        }

        return response()->json([
            'message' => 'Certification updated successfully.',
            'data' => $empCert->load('certification'),
        ]);
    }

    public function destroyEmployeeCertification(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
        $empCert = EmployeeCertification::findOrFail($id);
        $empCert->delete();

        return response()->json([
            'message' => 'Certification removed from employee.',
        ]);
    }

    public function verifyEmployeeCertification(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.certifications.manage');
        $empCert = EmployeeCertification::findOrFail($id);

        $empCert->update([
            'status' => 'valid',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $request->input('notes', $empCert->notes),
        ]);

        return response()->json([
            'message' => 'Certification verified.',
            'data' => $empCert->load(['certification', 'verifier']),
        ]);
    }

    public function downloadCertificationFile(string $id, ?string $mediaId = null)
    {
        $this->authorizeHrRead('hr.certifications.view');
        $empCert = EmployeeCertification::findOrFail($id);

        $media = $mediaId
            ? $empCert->media()->findOrFail($mediaId)
            : $empCert->getFirstMedia('certificate_files');

        if (! $media) {
            abort(404, 'No certificate file attached.');
        }

        return $media->toResponse(request());
    }
}
