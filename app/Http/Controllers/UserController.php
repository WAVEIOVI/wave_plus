<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Hr\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    /**
     * Paginated user list with search & filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(
            User::with([
                'roles',
                'employee:id,first_name,last_name,employee_number,work_email',
                'client:id,legal_name,commercial_name,client_id',
                'supplier:id,legal_name,commercial_name,supplier_id',
                'organisation:id,legal_name,commercial_name,organisation_id',
                'contact:id,first_name,last_name,email,job_title',
                'clientSites:id,name,site_code',
                'clients:id,legal_name,commercial_name,client_id',
            ])
        )
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::exact('account_status'),
                AllowedFilter::exact('user_type'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('first_name'),
                AllowedSort::field('last_name'),
                AllowedSort::field('email'),
                AllowedSort::field('created_at'),
                AllowedSort::field('account_status'),
            ])
            ->defaultSort('id');

        // Legacy param support
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($status = $request->input('status')) {
            $query->where('account_status', $status);
        }

        if ($userType = $request->input('user_type') ?? $request->input('userType')) {
            $query->where('user_type', $userType);
        }

        // Legacy sorting
        $sortBy = $request->input('sortBy');
        $orderBy = $request->input('orderBy', 'asc');
        $allowedSorts = ['id', 'name', 'first_name', 'last_name', 'email', 'created_at', 'account_status'];
        if ($sortBy && in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $orderBy === 'desc' ? 'desc' : 'asc');
        }

        // Pagination
        $perPage = $request->input('itemsPerPage', 10);
        if ($perPage == -1) {
            $users = $query->get();
            $total = $users->count();
        } else {
            $paginated = $query->paginate($perPage);
            $users = $paginated->items();
            $total = $paginated->total();
        }

        // Format for frontend
        $formatted = collect($users)->map(fn ($user) => $this->formatUser($user));

        return response()->json([
            'users' => $formatted,
            'totalUsers' => $total,
        ]);
    }

    /**
     * Provide creation/filtering options for User Creation Wizard.
     */
    public function creationOptions(): JsonResponse
    {
        $employees = Employee::select('id', 'first_name', 'last_name', 'employee_number', 'work_email')
            ->where('status', 'active')
            ->orderBy('last_name')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => trim("{$emp->first_name} {$emp->last_name}"),
                'employee_number' => $emp->employee_number,
                'email' => $emp->work_email,
            ]);

        $clients = Client::select('id', 'legal_name', 'commercial_name', 'client_id')
            ->where('status', 'active')
            ->with([
                'sites:id,client_id,name,site_code',
                'contacts:id,contactable_id,first_name,last_name,email,job_title',
            ])
            ->orderBy('legal_name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->commercial_name ?: $c->legal_name,
                'client_id' => $c->client_id,
                'sites' => $c->sites->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'code' => $s->site_code,
                ]),
                'contacts' => $c->contacts->map(fn ($ct) => [
                    'id' => $ct->id,
                    'name' => trim("{$ct->first_name} {$ct->last_name}"),
                    'email' => $ct->email,
                    'job_title' => $ct->job_title,
                ]),
            ]);

        $suppliers = Supplier::select('id', 'legal_name', 'commercial_name', 'supplier_id')
            ->where('status', 'active')
            ->with([
                'contacts:id,contactable_id,first_name,last_name,email,job_title',
            ])
            ->orderBy('legal_name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->commercial_name ?: $s->legal_name,
                'supplier_id' => $s->supplier_id,
                'contacts' => $s->contacts->map(fn ($ct) => [
                    'id' => $ct->id,
                    'name' => trim("{$ct->first_name} {$ct->last_name}"),
                    'email' => $ct->email,
                    'job_title' => $ct->job_title,
                ]),
            ]);

        $organisations = Organisation::select('id', 'legal_name', 'commercial_name', 'organisation_id')
            ->orderBy('legal_name')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->commercial_name ?: $o->legal_name,
                'organisation_id' => $o->organisation_id,
            ]);

        $roles = Role::pluck('name');

        $userTypes = [
            [
                'value' => 'super_admin',
                'title' => 'Super Admin',
                'badge' => 'primary',
                'desc' => 'Total unrestricted platform access across all modules.',
                'requiresAttachment' => false,
            ],
            [
                'value' => 'admin',
                'title' => 'Admin',
                'badge' => 'secondary',
                'desc' => 'Administrative privileges for management and operations.',
                'requiresAttachment' => false,
            ],
            [
                'value' => 'employee',
                'title' => 'Employee',
                'badge' => 'info',
                'desc' => 'Attached to an internal staff member/employee.',
                'requiresAttachment' => true,
                'attachmentEntity' => 'employee',
            ],
            [
                'value' => 'standard_client',
                'title' => 'Standard Client',
                'badge' => 'success',
                'desc' => 'Direct client account with full access to their company data.',
                'requiresAttachment' => true,
                'attachmentEntity' => 'client',
            ],
            [
                'value' => 'site_responsible',
                'title' => 'Site Responsible',
                'badge' => 'warning',
                'desc' => 'Sub-client user restricted to specific assigned client site(s).',
                'requiresAttachment' => true,
                'attachmentEntity' => 'site_responsible',
            ],
            [
                'value' => 'client_group',
                'title' => 'Client Group',
                'badge' => 'info',
                'desc' => 'Holding or parent account managing multiple attached clients.',
                'requiresAttachment' => true,
                'attachmentEntity' => 'client_group',
            ],
            [
                'value' => 'organisation',
                'title' => 'Organisation',
                'badge' => 'error',
                'desc' => 'Civil protection or audit body with scoped multi-client view.',
                'requiresAttachment' => true,
                'attachmentEntity' => 'organisation',
            ],
            [
                'value' => 'supplier',
                'title' => 'Supplier',
                'badge' => 'secondary',
                'desc' => 'Vendor partner access limited to supplier purchases & RFQs.',
                'requiresAttachment' => true,
                'attachmentEntity' => 'supplier',
            ],
        ];

        return response()->json([
            'employees' => $employees,
            'clients' => $clients,
            'suppliers' => $suppliers,
            'organisations' => $organisations,
            'roles' => $roles,
            'userTypes' => $userTypes,
        ]);
    }

    /**
     * Create a new user (internal registration).
     */
    public function store(Request $request): JsonResponse
    {
        $input = $this->normalizeInput($request->all());
        $request->replace($input);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'user_type' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'is_active' => 'boolean',
            'is_suspended' => 'boolean',
            'suspension_reason' => 'nullable|string',
            'can_login' => 'boolean',
            'account_status' => 'nullable|in:active,inactive,pending,suspended',
            'email_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'role' => 'nullable|string|exists:roles,name',
            'employee_id' => 'nullable|ulid|exists:hr_employees,id',
            'client_id' => 'nullable|ulid|exists:clients,id',
            'supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'organisation_id' => 'nullable|ulid|exists:organisations,id',
            'contact_id' => 'nullable|ulid|exists:bp_contacts,id',
            'access_scope' => 'nullable|array',
            'client_site_ids' => 'nullable|array',
            'client_site_ids.*' => 'ulid|exists:client_sites,id',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'ulid|exists:clients,id',
        ]);

        $validated['name'] = trim("{$validated['first_name']} {$validated['last_name']}");
        $validated['password'] = Hash::make($validated['password']);

        $roleName = $validated['role'] ?? null;
        $clientSiteIds = $validated['client_site_ids'] ?? [];
        $clientIds = $validated['client_ids'] ?? [];

        $currentUser = $request->user();
        if ($roleName === 'Super Admin' || ($validated['user_type'] ?? '') === 'super_admin') {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can create Super Admin accounts.');
        }

        unset(
            $validated['role'],
            $validated['password_confirmation'],
            $validated['client_site_ids'],
            $validated['client_ids']
        );

        $user = DB::transaction(function () use ($validated, $roleName, $clientSiteIds, $clientIds) {
            $user = User::create($validated);

            if ($roleName) {
                $user->assignRole($roleName);
            }

            if (!empty($clientSiteIds)) {
                $user->clientSites()->sync($clientSiteIds);
            }

            if (!empty($clientIds)) {
                $user->clients()->sync($clientIds);
            }

            if (!empty($validated['employee_id'])) {
                Employee::where('id', $validated['employee_id'])->update(['user_id' => $user->id]);
            }

            return $user;
        });

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $this->formatUser($this->loadUserRelations($user)),
        ], 201);
    }

    /**
     * Show a single user.
     */
    public function show(string $id): JsonResponse
    {
        $user = $this->loadUserRelations(User::findOrFail($id));

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Update user profile.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $input = $this->normalizeInput($request->all());
        $request->replace($input);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'user_type' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'is_active' => 'boolean',
            'is_suspended' => 'boolean',
            'suspension_reason' => 'nullable|string',
            'can_login' => 'boolean',
            'account_status' => 'nullable|in:active,inactive,pending,suspended',
            'email_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'role' => 'nullable|string|exists:roles,name',
            'employee_id' => 'nullable|ulid|exists:hr_employees,id',
            'client_id' => 'nullable|ulid|exists:clients,id',
            'supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'organisation_id' => 'nullable|ulid|exists:organisations,id',
            'contact_id' => 'nullable|ulid|exists:bp_contacts,id',
            'access_scope' => 'nullable|array',
            'client_site_ids' => 'nullable|array',
            'client_site_ids.*' => 'ulid|exists:client_sites,id',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'ulid|exists:clients,id',
        ]);

        $currentUser = $request->user();
        $targetIsSuperAdmin = $user->hasRole('Super Admin') || $user->user_type === 'super_admin';

        // Only Super Admin can modify a Super Admin user
        if ($targetIsSuperAdmin) {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can modify Super Admin accounts.');
        }

        $roleName = $validated['role'] ?? null;

        // Only Super Admin can assign Super Admin role or user_type
        if ($roleName === 'Super Admin' || ($validated['user_type'] ?? '') === 'super_admin') {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can assign Super Admin privileges.');
        }

        // Prevent self-escalation: Non-Super Admins cannot change their own roles or privileges
        if ($currentUser && $currentUser->id === $user->id && ! $currentUser->hasRole('Super Admin')) {
            if ($roleName && $roleName !== $currentUser->getRoleNames()->first()) {
                abort(403, 'You cannot modify your own roles or permissions.');
            }
        }

        // Protect the last active Super Admin
        if ($targetIsSuperAdmin) {
            $isDemoting = $roleName && $roleName !== 'Super Admin';
            $isDeactivating = (isset($validated['is_active']) && ! $validated['is_active'])
                || (isset($validated['can_login']) && ! $validated['can_login'])
                || (isset($validated['account_status']) && $validated['account_status'] !== 'active')
                || (isset($validated['is_suspended']) && $validated['is_suspended']);

            if ($isDemoting || $isDeactivating) {
                $activeCount = User::role('Super Admin')
                    ->where('is_active', true)
                    ->where('can_login', true)
                    ->where('account_status', 'active')
                    ->count();

                if ($activeCount <= 1) {
                    abort(422, 'Cannot demote or deactivate the last active Super Admin.');
                }
            }
        }

        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $firstName = $validated['first_name'] ?? $user->first_name;
            $lastName = $validated['last_name'] ?? $user->last_name;
            $validated['name'] = trim("{$firstName} {$lastName}");
        }

        $clientSiteIds = $validated['client_site_ids'] ?? null;
        $clientIds = $validated['client_ids'] ?? null;

        unset($validated['role'], $validated['client_site_ids'], $validated['client_ids']);

        DB::transaction(function () use ($user, $validated, $roleName, $clientSiteIds, $clientIds) {
            $user->update($validated);

            if ($roleName) {
                $user->syncRoles([$roleName]);
            }

            if ($clientSiteIds !== null) {
                $user->clientSites()->sync($clientSiteIds);
            }

            if ($clientIds !== null) {
                $user->clients()->sync($clientIds);
            }

            if (isset($validated['employee_id'])) {
                Employee::where('id', $validated['employee_id'])->update(['user_id' => $user->id]);
            }
        });

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->formatUser($this->loadUserRelations($user->fresh())),
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request, string $id): JsonResponse
    {
        $input = $this->normalizeInput($request->all());
        $request->replace($input);

        $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        if ($user->hasRole('Super Admin') || $user->user_type === 'super_admin') {
            abort_unless($currentUser && ($currentUser->hasRole('Super Admin') || $currentUser->id === $user->id), 403, 'Only a Super Admin can change a Super Admin password.');
        }

        $user->changePassword($request->password);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Delete (deactivate) a user.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = request()->user();
        $targetIsSuperAdmin = $user->hasRole('Super Admin') || $user->user_type === 'super_admin';

        if ($targetIsSuperAdmin) {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can deactivate a Super Admin account.');

            $activeCount = User::role('Super Admin')
                ->where('is_active', true)
                ->where('can_login', true)
                ->where('account_status', 'active')
                ->count();

            if ($activeCount <= 1) {
                abort(422, 'Cannot deactivate the last active Super Admin.');
            }
        }

        $user->update([
            'is_active' => false,
            'can_login' => false,
            'account_status' => 'inactive',
            'is_suspended' => false,
        ]);

        $user->revokeAllAccess();

        return response()->json([
            'message' => 'User deactivated successfully.',
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function loadUserRelations(User $user): User
    {
        return $user->loadMissing([
            'roles',
            'employee:id,first_name,last_name,employee_number,work_email',
            'client:id,legal_name,commercial_name,client_id',
            'supplier:id,legal_name,commercial_name,supplier_id',
            'organisation:id,legal_name,commercial_name,organisation_id',
            'contact:id,first_name,last_name,email,job_title',
            'clientSites:id,name,site_code',
            'clients:id,legal_name,commercial_name,client_id',
        ]);
    }

    private function normalizeInput(array $input): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            $normalized[Str::snake($key)] = $value;
        }

        return $normalized;
    }

    /**
     * Format user data for frontend consumption.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'fullName' => $user->full_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'employee_id' => $user->employee_id,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'name' => trim("{$user->employee->first_name} {$user->employee->last_name}"),
                'employee_number' => $user->employee->employee_number,
                'email' => $user->employee->work_email,
            ] : null,
            'client_id' => $user->client_id,
            'client' => $user->client ? [
                'id' => $user->client->id,
                'name' => $user->client->commercial_name ?: $user->client->legal_name,
                'client_id' => $user->client->client_id,
            ] : null,
            'supplier_id' => $user->supplier_id,
            'supplier' => $user->supplier ? [
                'id' => $user->supplier->id,
                'name' => $user->supplier->commercial_name ?: $user->supplier->legal_name,
                'supplier_id' => $user->supplier->supplier_id,
            ] : null,
            'organisation_id' => $user->organisation_id,
            'organisation' => $user->organisation ? [
                'id' => $user->organisation->id,
                'name' => $user->organisation->commercial_name ?: $user->organisation->legal_name,
                'organisation_id' => $user->organisation->organisation_id,
            ] : null,
            'contact_id' => $user->contact_id,
            'contact' => $user->contact ? [
                'id' => $user->contact->id,
                'name' => trim("{$user->contact->first_name} {$user->contact->last_name}"),
                'email' => $user->contact->email,
                'job_title' => $user->contact->job_title,
            ] : null,
            'client_site_ids' => $user->clientSites ? $user->clientSites->pluck('id')->values()->all() : [],
            'client_sites' => $user->clientSites ? $user->clientSites->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->site_code,
            ]) : [],
            'client_ids' => $user->clients ? $user->clients->pluck('id')->values()->all() : [],
            'clients' => $user->clients ? $user->clients->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->commercial_name ?: $c->legal_name,
                'client_id' => $c->client_id,
            ]) : [],
            'access_scope' => $user->access_scope,
            'date_of_birth' => $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('Y-m-d') : null,
            'gender' => $user->gender,
            'is_active' => (bool) $user->is_active,
            'is_suspended' => (bool) $user->is_suspended,
            'suspension_reason' => $user->suspension_reason,
            'can_login' => (bool) $user->can_login,
            'account_status' => $user->account_status,
            'email_notifications_enabled' => (bool) $user->email_notifications_enabled,
            'sms_notifications_enabled' => (bool) $user->sms_notifications_enabled,
            'role' => $user->getRoleNames()->first() ?? 'N/A',
            'roles' => $user->getRoleNames(),
            'avatar' => null,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
