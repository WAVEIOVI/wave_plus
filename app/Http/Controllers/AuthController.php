<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Login user and create token.
     *
     * Returns accessToken, userData, and userAbilityRules (CASL format)
     * expected by the Vuexy frontend.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid credentials.',
                'errors' => [
                    'email' => ['The provided credentials do not match our records.'],
                ],
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Enforce account deactivation during authentication
        if (! $user->isAuthorizedToLogin()) {
            Auth::guard('web')->logout();
            $user->revokeAllAccess();

            $statusMessage = match ($user->account_status) {
                'suspended' => 'Your account has been suspended.',
                'pending' => 'Your account is pending review or approval.',
                default => 'Your account has been deactivated.',
            };

            return response()->json([
                'message' => $statusMessage,
                'errors' => [
                    'email' => [$statusMessage],
                ],
            ], 403);
        }

        // Single-session / cleanup previous personal access tokens
        $user->tokens()->delete();

        // For stateful first-party browser sessions, regenerate session ID
        $isStatefulSpa = $request->hasSession() && ($request->hasHeader('referer') || $request->isXmlHttpRequest());
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Return personal-access token only for genuine non-browser/API clients or automated tests without sessions
        $token = (! $isStatefulSpa || $request->boolean('issue_token') || $request->header('X-API-Client'))
            ? $user->createToken('auth-token')->plainTextToken
            : null;

        $response = [
            'message' => 'Login successful.',
            'userData' => $this->formatUserData($user),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'roles' => $user->getRoleNames()->values()->all(),
            'userAbilityRules' => $this->mapPermissionsToCasl($user),
        ];

        if ($token !== null) {
            $response['accessToken'] = $token;
        }

        return response()->json($response);
    }

    /**
     * Register a new user.
     *
     * Assigns the default "User" role and returns the same
     * response shape as login for immediate authentication.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
            'can_login' => true,
            'account_status' => 'active',
        ]);

        // Assign default role
        $user->assignRole('User');

        if ($request->hasSession()) {
            $request->session()->regenerate();
            Auth::login($user);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            'userData' => $this->formatUserData($user),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'roles' => $user->getRoleNames()->values()->all(),
            'userAbilityRules' => $this->mapPermissionsToCasl($user),
        ], 201);
    }

    /**
     * Logout user (revoke current token and/or invalidate session).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Get the authenticated user with roles and permissions.
     */
    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'userData' => $this->formatUserData($user),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'roles' => $user->getRoleNames()->values()->all(),
            'userAbilityRules' => $this->mapPermissionsToCasl($user),
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * Map Spatie permissions to CASL ability rules.
     *
     * Convention: Spatie permissions are named "action-subject"
     * (e.g. "edit-users" → { action: "edit", subject: "users" }).
     *
     * Super Admin gets the CASL wildcard: [{ action: "manage", subject: "all" }].
     */
    private function mapPermissionsToCasl(User $user): array
    {
        // Super Admin shortcut — full access
        if ($user->hasRole('Super Admin')) {
            return [['action' => 'manage', 'subject' => 'all']];
        }

        return $user->getAllPermissions()
            ->map(function ($permission) {
                if (str_starts_with($permission->name, 'hr.')) {
                    [, $domain, $grant] = array_pad(explode('.', $permission->name, 3), 3, null);

                    $subject = $domain === 'fleet' ? 'Fleet' : 'PeopleSkills';
                    $rules = [['action' => 'read', 'subject' => $subject]];

                    $actions = match ($grant) {
                        'create', 'request' => ['create'],
                        'manage' => ['create', 'update'],
                        'edit', 'correct', 'approve', 'assign', 'hire' => ['update'],
                        'archive' => ['delete'],
                        default => [],
                    };

                    foreach ($actions as $action) {
                        $rules[] = ['action' => $action, 'subject' => $subject];
                    }

                    return $rules;
                }

                // Split on the first hyphen: "edit-users" → ["edit", "users"]
                $parts = explode('-', $permission->name, 2);

                $rules = [[
                    'action' => $parts[0],
                    'subject' => $parts[1] ?? 'all',
                ]];

                if ($permission->name === 'read-PeopleSkills') {
                    $rules[] = ['action' => 'read', 'subject' => 'Fleet'];
                }

                return $rules;
            })
            ->flatten(1)
            ->values()
            ->toArray();
    }

    /**
     * Format user data for the frontend cookie storage.
     */
    private function formatUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'fullName' => $user->full_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'User',
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'is_active' => $user->is_active,
            'account_status' => $user->account_status,
            'user_type' => $user->user_type,
            'avatar' => null,
        ];
    }
}
