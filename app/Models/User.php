<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    use HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'user_type',
        'employee_id',
        'client_id',
        'supplier_id',
        'organisation_id',
        'contact_id',
        'access_scope',
        'date_of_birth',
        'gender',
        'is_active',
        'is_suspended',
        'suspension_reason',
        'can_login',
        'account_status',
        'email_notifications_enabled',
        'sms_notifications_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'is_suspended' => 'boolean',
            'can_login' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'access_scope' => 'array',
        ];
    }

    // ─── Accessors ──────────────────────────────────────────────────────

    /**
     * Full name accessor — prefers first_name + last_name, falls back to name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }

        return $this->name ?? '';
    }

    // ─── Scopes ─────────────────────────────────────────────────────────

    /**
     * Scope to only active users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Relationships ──────────────────────────────────────────────────

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\BusinessPartners\Models\Client::class, 'client_id');
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\BusinessPartners\Models\Supplier::class, 'supplier_id');
    }

    public function organisation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\BusinessPartners\Models\Organisation::class, 'organisation_id');
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\BusinessPartners\Models\BpContact::class, 'contact_id');
    }

    public function clientSites(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\ClientAssets\Models\ClientSite::class, 'user_client_sites', 'user_id', 'client_site_id');
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\BusinessPartners\Models\Client::class, 'user_clients', 'user_id', 'client_id');
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Provide active defaults on creation if attributes are unspecified
            if ($user->account_status === null && $user->is_active === null && $user->can_login === null) {
                $user->account_status = 'active';
                $user->is_active = true;
                $user->can_login = true;
                $user->is_suspended = false;
            }

            // Keep can_login, is_active, and account_status authoritative and consistent
            if ($user->account_status === 'active') {
                if ($user->is_active === false || $user->can_login === false) {
                    $user->account_status = 'inactive';
                    $user->is_active = false;
                    $user->can_login = false;
                } else {
                    $user->is_active = true;
                    $user->can_login = true;
                    $user->is_suspended = false;
                }
            } elseif ($user->account_status === 'suspended') {
                $user->is_active = false;
                $user->can_login = false;
                $user->is_suspended = true;
            } elseif ($user->account_status === 'inactive') {
                $user->is_active = false;
                $user->can_login = false;
                $user->is_suspended = false;
            } elseif ($user->account_status === 'pending') {
                $user->can_login = false;
            }

            if ($user->is_suspended) {
                $user->is_active = false;
                $user->can_login = false;
                $user->account_status = 'suspended';
            }
        });

        static::updated(function (User $user) {
            if (! $user->isAuthorizedToLogin()) {
                $user->revokeAllAccess();
            }
        });
    }

    // ─── Methods ────────────────────────────────────────────────────────

    /**
     * Check if user is currently authorized to log in.
     */
    public function isAuthorizedToLogin(): bool
    {
        return (bool) $this->can_login
            && (bool) $this->is_active
            && ! (bool) $this->is_suspended
            && $this->account_status === 'active';
    }

    /**
     * Revoke all active sessions and personal access tokens.
     */
    public function revokeAllAccess(): void
    {
        $this->tokens()->delete();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    /**
     * Securely change the user's password.
     */
    public function changePassword(string $newPassword): void
    {
        $this->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}

