<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Certification extends Model
{
    use HasFactory, HasUlids, HasTranslations;

    protected $table = 'hr_certifications';

    public array $translatable = ['name', 'issuing_authority', 'description'];

    protected $fillable = [
        'code',
        'name',
        'issuing_authority',
        'description',
        'validity_period_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employeeCertifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class, 'certification_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
