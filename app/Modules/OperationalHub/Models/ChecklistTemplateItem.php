<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ChecklistTemplateItem extends Model
{
    use HasTranslations;
    use HasUlids;

    protected $table = 'checklist_template_items';

    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'template_id',
        'title',
        'description',
        'answer_type',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }
}
