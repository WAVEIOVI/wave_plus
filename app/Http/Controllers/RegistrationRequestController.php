<?php

namespace App\Http\Controllers;

use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegistrationRequestController extends Controller
{
    /**
     * Public submission of a registration request by a visitor (unauthenticated).
     */
    public function publicStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:150',
            'tax_id' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'requested_user_type' => 'nullable|string|in:standard_client,site_responsible,client_group,organisation,supplier,employee,other',
            'requested_scope_details' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        $normalizedEmail = mb_strtolower(trim($validated['email']));
        $firstName = trim($validated['first_name']);
        $lastName = trim($validated['last_name']);

        // Prevent account enumeration & duplicate spam: return generic success without revealing existing user
        $userExists = User::where('email', $normalizedEmail)->exists();
        $pendingRequest = RegistrationRequest::where('email', $normalizedEmail)
            ->whereIn('status', ['pending', 'under_review'])
            ->first();

        if ($userExists || $pendingRequest) {
            return response()->json([
                'message' => 'Your registration request has been submitted successfully. A Wave Plus administrator will review your information.',
                'reference' => $pendingRequest?->reference ?? 'WP-REG-'.strtoupper(Str::random(8)),
            ], 201);
        }

        $registrationRequest = DB::transaction(function () use ($validated, $request, $normalizedEmail, $firstName, $lastName) {
            return RegistrationRequest::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $normalizedEmail,
                'phone' => isset($validated['phone']) ? trim($validated['phone']) : null,
                'company_name' => isset($validated['company_name']) ? trim($validated['company_name']) : null,
                'tax_id' => isset($validated['tax_id']) ? trim($validated['tax_id']) : null,
                'industry' => isset($validated['industry']) ? trim($validated['industry']) : null,
                'address' => isset($validated['address']) ? trim($validated['address']) : null,
                'city' => isset($validated['city']) ? trim($validated['city']) : null,
                'country' => isset($validated['country']) ? trim($validated['country']) : null,
                'website' => isset($validated['website']) ? trim($validated['website']) : null,
                'requested_user_type' => $validated['requested_user_type'] ?? 'other',
                'requested_scope_details' => $validated['requested_scope_details'] ?? null,
                'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
                'status' => 'pending',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        });

        return response()->json([
            'message' => 'Your registration request has been submitted successfully. A Wave Plus administrator will review your information.',
            'reference' => $registrationRequest->reference,
            'request' => $registrationRequest,
        ], 201);
    }

    /**
     * Admin: Paginated listing of registration requests with filtering and stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RegistrationRequest::with(['reviewer:id,name,email', 'createdUser:id,name,email']);

        // Search
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // User Type filter
        if ($type = $request->input('requested_user_type')) {
            $query->where('requested_user_type', $type);
        }

        // Sorting
        $sortBy = $request->input('sortBy', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');
        $allowedSorts = ['id', 'reference', 'first_name', 'last_name', 'email', 'company_name', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $orderBy === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Stats summary
        $stats = [
            'total' => RegistrationRequest::count(),
            'pending' => RegistrationRequest::where('status', 'pending')->count(),
            'under_review' => RegistrationRequest::where('status', 'under_review')->count(),
            'approved' => RegistrationRequest::where('status', 'approved')->count(),
            'rejected' => RegistrationRequest::where('status', 'rejected')->count(),
        ];

        // Pagination
        $perPage = $request->input('itemsPerPage', 10);
        if ($perPage == -1) {
            $items = $query->get();
            $total = $items->count();
        } else {
            $paginated = $query->paginate($perPage);
            $items = $paginated->items();
            $total = $paginated->total();
        }

        return response()->json([
            'requests' => $items,
            'totalRequests' => $total,
            'stats' => $stats,
        ]);
    }

    /**
     * Admin: Show a single registration request.
     */
    public function show(string $id): JsonResponse
    {
        $requestItem = RegistrationRequest::with(['reviewer:id,name,email', 'createdUser:id,name,email'])->findOrFail($id);

        return response()->json([
            'request' => $requestItem,
        ]);
    }

    /**
     * Admin: Create a registration request internally from the dashboard.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:150',
            'tax_id' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'requested_user_type' => 'nullable|string',
            'requested_scope_details' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,under_review,approved,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = 'Internal Admin Entry';

        $registrationRequest = RegistrationRequest::create($validated);

        return response()->json([
            'message' => 'Registration request created successfully.',
            'request' => $registrationRequest,
        ], 201);
    }

    /**
     * Admin: Update status or review notes.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $item = RegistrationRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,approved,rejected',
            'admin_notes' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ]);

        $item->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $item->admin_notes,
            'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? $item->rejection_reason) : null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Registration request status updated successfully.',
            'request' => $item->fresh(['reviewer', 'createdUser']),
        ]);
    }

    /**
     * Admin: Approve request and convert to verified User.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $regRequest = RegistrationRequest::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'string', Password::defaults()],
            'user_type' => 'required|string|max:50',
            'role' => 'nullable|string|exists:roles,name',
            'employee_id' => 'nullable|ulid|exists:hr_employees,id',
            'client_id' => 'nullable|ulid|exists:clients,id',
            'supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'organisation_id' => 'nullable|ulid|exists:organisations,id',
            'contact_id' => 'nullable|ulid|exists:bp_contacts,id',
            'client_site_ids' => 'nullable|array',
            'client_site_ids.*' => 'ulid|exists:client_sites,id',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'ulid|exists:clients,id',
            'admin_notes' => 'nullable|string',
        ]);

        $currentUser = $request->user();
        if (($validated['role'] ?? '') === 'Super Admin' || ($validated['user_type'] ?? '') === 'super_admin') {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can approve and assign Super Admin accounts.');
        }

        return DB::transaction(function () use ($regRequest, $validated) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'user_type' => $validated['user_type'],
                'employee_id' => $validated['employee_id'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'organisation_id' => $validated['organisation_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]);

            // Assign role
            if (!empty($validated['role'])) {
                $user->assignRole($validated['role']);
            }

            // Sync client sites for site_responsible
            if (!empty($validated['client_site_ids'])) {
                $user->clientSites()->sync($validated['client_site_ids']);
            }

            // Sync clients for client_group / organisation
            if (!empty($validated['client_ids'])) {
                $user->clients()->sync($validated['client_ids']);
            }

            // If employee, link back to employee model user_id
            if (!empty($validated['employee_id'])) {
                \App\Modules\Hr\Models\Employee::where('id', $validated['employee_id'])
                    ->update(['user_id' => $user->id]);
            }

            // Update registration request
            $regRequest->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'created_user_id' => $user->id,
                'admin_notes' => $validated['admin_notes'] ?? $regRequest->admin_notes,
            ]);

            return response()->json([
                'message' => 'Registration request approved and verified user account created successfully.',
                'user' => $user->load('roles'),
                'request' => $regRequest->fresh(['reviewer', 'createdUser']),
            ], 201);
        });
    }

    /**
     * Admin: Delete registration request.
     */
    public function destroy(string $id): JsonResponse
    {
        $item = RegistrationRequest::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Registration request removed successfully.',
        ]);
    }
}
