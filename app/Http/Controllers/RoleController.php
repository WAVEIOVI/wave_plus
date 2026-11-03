<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * List all roles with their permissions (as name strings) and user count.
     */
    public function index()
    {
        try {
            Log::info('RoleController@index: Fetching roles');

            $tableNames = config('permission.table_names');

            $roles = Role::with(['permissions'])
                ->select($tableNames['roles'].'.*')
                ->selectSub(function ($query) use ($tableNames) {
                    $query->from($tableNames['model_has_roles'])
                        ->selectRaw('count(*)')
                        ->whereColumn($tableNames['model_has_roles'].'.role_id', $tableNames['roles'].'.id');
                }, 'users_count')
                ->get();

            // Frontend expects { data: [...] }
            return response()->json(['data' => $roles]);
        } catch (\Throwable $e) {
            Log::error('RoleController@index Error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['message' => 'Failed to retrieve roles.'], 500);
        }
    }

    /**
     * Create a new role with optional permissions.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $currentUser = $request->user();
        if ($request->name === 'Super Admin' || $request->name === 'Admin') {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Cannot create system roles.');
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => "Role '{$role->name}' created.",
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
            ],
        ], 201);
    }

    /**
     * Show a single role with its permissions.
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->withCount('users')->findOrFail($id);

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ]);
    }

    /**
     * Update a role name and sync its existing permissions.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $currentUser = $request->user();

        // Prevent ordinary Admin users from modifying system roles
        if (in_array($role->name, ['Super Admin', 'Admin'], true)) {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can modify protected system roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Prevent renaming protected system roles
        if (in_array($role->name, ['Super Admin', 'Admin'], true) && $validated['name'] !== $role->name) {
            return response()->json(['message' => 'Cannot rename protected system roles.'], 403);
        }

        DB::transaction(function () use ($role, $validated) {
            $role->update(['name' => $validated['name']]);
            $role->syncPermissions($validated['permissions']);
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => "Role '{$role->name}' updated.",
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Sync permissions for a role (attach/detach only).
     *
     * No new permissions are created — it only accepts existing permission names.
     */
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $currentUser = $request->user();

        // Prevent ordinary Admin users from modifying system role permissions
        if (in_array($role->name, ['Super Admin', 'Admin'], true)) {
            abort_unless($currentUser && $currentUser->hasRole('Super Admin'), 403, 'Only a Super Admin can modify protected system-role permissions.');
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Only sync with existing permissions — never creates new ones
        $role->syncPermissions($request->permissions);

        // Clear the permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => "Permissions synced for role '{$role->name}'.",
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * List all existing permissions (read-only).
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()->map(function (Permission $perm) {
            return [
                'id' => $perm->id,
                'name' => $perm->name,
                'guard_name' => $perm->guard_name,
                'created_at' => $perm->created_at,
                'roles' => $perm->roles()->pluck('name'),
            ];
        });

        return response()->json([
            'permissions' => $permissions,
            'totalPermissions' => $permissions->count(),
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroy(string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        // Prevent deleting critical roles
        if (in_array($role->name, ['Super Admin', 'Admin'])) {
            return response()->json(['message' => 'Cannot delete system roles.'], 403);
        }

        // Check if role has users (safely without using relationship which might error)
        $hasUsers = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->id)
            ->exists();

        if ($hasUsers) {
            return response()->json(['message' => 'Cannot delete role because it is assigned to users.'], 409);
        }

        $role->delete();

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
