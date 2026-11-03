<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    // ── Predefined Template Categories ───────────────────────────────────
    public const CATEGORY_GENERAL_COMMUNICATION = 'general_communication';

    public const CATEGORY_QUOTATION_SUBMISSION = 'quotation_submission';

    public const CATEGORY_PURCHASE_ORDER_SUBMISSION = 'purchase_order_submission';

    public const CATEGORY_SALES_ORDER_COMMUNICATION = 'sales_order_communication';

    public const CATEGORY_INVOICE_DELIVERY = 'invoice_delivery';

    public const CATEGORY_PAYMENT_REMINDER = 'payment_reminder';

    public const CATEGORY_SUPPLIER_REQUEST = 'supplier_request';

    public const CATEGORY_DELIVERY_CONFIRMATION = 'delivery_confirmation';

    public const CATEGORY_MISSION_CONFIRMATION = 'mission_confirmation';

    public const CATEGORY_MAINTENANCE_NOTIFICATION = 'maintenance_notification';

    public const CATEGORY_SYSTEM_NOTIFICATION = 'system_notification';

    public const CATEGORY_CLIENT_PROFILE = 'client_profile';

    public const CATEGORY_SUPPLIER_PROFILE = 'supplier_profile';

    public const CATEGORY_ORGANISATION_PROFILE = 'organisation_profile';

    public const CATEGORY_SERVICE_PROVIDER_PROFILE = 'service_provider_profile';

    public const CATEGORIES = [
        self::CATEGORY_GENERAL_COMMUNICATION,
        self::CATEGORY_QUOTATION_SUBMISSION,
        self::CATEGORY_PURCHASE_ORDER_SUBMISSION,
        self::CATEGORY_SALES_ORDER_COMMUNICATION,
        self::CATEGORY_INVOICE_DELIVERY,
        self::CATEGORY_PAYMENT_REMINDER,
        self::CATEGORY_SUPPLIER_REQUEST,
        self::CATEGORY_DELIVERY_CONFIRMATION,
        self::CATEGORY_MISSION_CONFIRMATION,
        self::CATEGORY_MAINTENANCE_NOTIFICATION,
        self::CATEGORY_SYSTEM_NOTIFICATION,
        self::CATEGORY_CLIENT_PROFILE,
        self::CATEGORY_SUPPLIER_PROFILE,
        self::CATEGORY_ORGANISATION_PROFILE,
        self::CATEGORY_SERVICE_PROVIDER_PROFILE,
    ];

    protected $fillable = [
        'slug',
        'category',
        'language',
        'name',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'status',
        'description',
        'requires_record_context',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'template_id');
    }

    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    public function getDescriptionAttribute(): string
    {
        $raw = $this->body_text ?: strip_tags($this->body_html ?? '');
        $clean = (string) preg_replace('/\s+/', ' ', trim($raw));

        return \Illuminate\Support\Str::limit($clean, 140);
    }

    public function getRequiresRecordContextAttribute(): bool
    {
        $vars = $this->variables ?? [];
        foreach ($vars as $var) {
            if (is_string($var) && (str_starts_with($var, 'document.') || str_starts_with($var, 'record.'))) {
                return true;
            }
        }

        return false;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public static function findByCategoryAndLanguage(string $category, string $language = 'fr'): ?self
    {
        return static::query()
            ->active()
            ->category($category)
            ->language($language)
            ->first()
            ?? static::query()
                ->active()
                ->category($category)
                ->first();
    }
}
