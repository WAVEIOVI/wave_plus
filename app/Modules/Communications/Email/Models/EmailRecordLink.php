<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Finance\Models\SupplierInvoice;
use App\Modules\Hr\Models\Employee;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailRecordLink extends Model
{
    protected $table = 'email_associations';

    /**
     * Map of convenient aliases to full model class names.
     */
    public const SUPPORTED_RECORD_MAP = [
        'client' => Client::class,
        'supplier' => Supplier::class,
        'organisation' => Organisation::class,
        'organization' => Organisation::class,
        'service_provider' => ServiceProvider::class,
        'business_partner' => Client::class,
        'employee' => Employee::class,
        'user' => User::class,
        'quotation' => Quotation::class,
        'sales_order' => SalesOrder::class,
        'purchase_order' => PurchaseOrder::class,
        'sales_invoice' => SalesInvoice::class,
        'supplier_invoice' => SupplierInvoice::class,
        'invoice' => SalesInvoice::class,
        'mission' => Mission::class,
        'delivery_work_order' => DeliveryWorkOrder::class,
        'service_work_order' => ServiceWorkOrder::class,
        'goods_receipt_note' => GoodsReceiptNote::class,
        'contract' => Contract::class,
        'bp_contact' => BpContact::class,
        'contact' => BpContact::class,
        'bp_document' => \App\Modules\BusinessPartners\Models\BpDocument::class,
        'document' => \App\Modules\BusinessPartners\Models\BpDocument::class,
    ];

    protected $fillable = [
        'conversation_id',
        'email_message_id',
        'associable_type',
        'associable_id',
        'link_type',
        'is_confirmed',
        'confidence_score',
        'match_reason',
        'linked_by',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'confidence_score' => 'float',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(EmailConversation::class, 'conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }

    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function associable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve model class from string alias or class name.
     */
    public static function resolveModelClass(string $type): ?string
    {
        $normalized = strtolower(trim($type));
        if (isset(self::SUPPORTED_RECORD_MAP[$normalized])) {
            return self::SUPPORTED_RECORD_MAP[$normalized];
        }

        // Only allow explicit model classes that are defined in SUPPORTED_RECORD_MAP (no arbitrary classes)
        if (in_array($type, self::SUPPORTED_RECORD_MAP, true)) {
            return $type;
        }

        return null;
    }

    /**
     * Resolve short alias from model instance or class name.
     */
    public static function resolveRecordType(object|string $model): string
    {
        $class = is_object($model) ? get_class($model) : $model;

        return match ($class) {
            Client::class => 'client',
            Supplier::class => 'supplier',
            Organisation::class => 'organisation',
            ServiceProvider::class => 'service_provider',
            Quotation::class => 'quotation',
            SalesOrder::class => 'sales_order',
            PurchaseOrder::class => 'purchase_order',
            SalesInvoice::class => 'sales_invoice',
            SupplierInvoice::class => 'supplier_invoice',
            Mission::class => 'mission',
            Employee::class => 'employee',
            User::class => 'user',
            DeliveryWorkOrder::class => 'delivery_work_order',
            ServiceWorkOrder::class => 'service_work_order',
            GoodsReceiptNote::class => 'goods_receipt_note',
            Contract::class => 'contract',
            BpContact::class => 'bp_contact',
            default => class_basename($class),
        };
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('is_confirmed', true);
    }

    public function scopeUnconfirmed(Builder $query): Builder
    {
        return $query->where('is_confirmed', false);
    }

    public function scopeForRecord(Builder $query, string $type, string|int $id): Builder
    {
        $resolvedClass = self::resolveModelClass($type) ?? $type;

        return $query->where(function (Builder $q) use ($resolvedClass, $type, $id) {
            $q->where(function (Builder $sub) use ($resolvedClass, $id) {
                $sub->where('associable_type', $resolvedClass)
                    ->where('associable_id', (string) $id);
            })->orWhere(function (Builder $sub) use ($type, $id) {
                $sub->where('associable_type', strtolower($type))
                    ->where('associable_id', (string) $id);
            });
        });
    }

    public function scopeForConversation(Builder $query, string $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function confirm(?string $userId = null): self
    {
        $this->update([
            'is_confirmed' => true,
            'confidence_score' => 1.00,
            'linked_by' => $userId ?? $this->linked_by,
        ]);

        return $this;
    }
}
