<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Authoritative Internal Employee Model
 */
class Employee extends Model implements HasMedia
{
    use GeneratesReference, HasFactory, HasUlids, HasTranslations, InteractsWithMedia, SoftDeletes;

    protected $table = 'hr_employees';

    public array $translatable = ['first_name', 'middle_name', 'last_name', 'preferred_name'];

    public const DOCUMENT_TYPE_PREFIX = 'EMP';

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'employee_number',
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'gender',
        'date_of_birth',
        'nationality',
        'work_email',
        'personal_email',
        'work_phone',
        'personal_phone',
        'address',
        'hire_date',
        'termination_date',
        'status',
        'department_id',
        'position_id',
        'manager_id',
        'current_team_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
        ];
    }

    // ─── Reference Generation ──────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'employee_number';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('EMP-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return static::DOCUMENT_TYPE_PREFIX;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('documents')
            ->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(400)
            ->height(400)
            ->nonQueued();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        /** @var Media|null $media */
        $media = $this->getFirstMedia('avatar');

        return $media ? $media->getUrl() : null;
    }

    // ─── Accessors ────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        $names = array_filter([$this->first_name, $this->middle_name, $this->last_name]);

        return implode(' ', $names);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function currentPosition(): BelongsTo
    {
        return $this->position();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(WorkTeam::class, 'current_team_id');
    }

    public function employmentHistory(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class, 'employee_id')->orderByDesc('start_date');
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(WorkTeamMember::class, 'employee_id');
    }

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'employee_id')->with('skill');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class, 'employee_id')->with('certification');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'employee_id')->with('shift');
    }

    public function clockEvents(): HasMany
    {
        return $this->hasMany(TimeClockEvent::class, 'employee_id')->orderByDesc('event_time');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id')->orderByDesc('date');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id')->with('leaveType');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id')->orderByDesc('start_date');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class, 'employee_id')->with('session.program');
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(EmployeeAppraisal::class, 'employee_id')->orderByDesc('created_at');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForDepartment($query, string $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}
