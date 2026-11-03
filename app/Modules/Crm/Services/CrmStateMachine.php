<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

/**
 * Canonical WAVE PLUS CRM commercial state machines.
 * User PATCH /status may only follow these graphs. Conversion, acceptance,
 * GCS/LSA generation, and fulfillment use dedicated methods.
 */
class CrmStateMachine
{
    public const QUOTATION = [
        'draft' => ['sent', 'expired'],
        'sent' => ['under_review', 'rejected', 'expired'],
        'under_review' => ['accepted', 'rejected', 'sent', 'expired'],
        'accepted' => [],
        'rejected' => ['draft'],
        'expired' => ['draft'],
        'converted' => [],
        'archived' => [],
    ];

    public const PRICE_REQUEST = [
        'new' => ['pending_internal', 'cancelled'],
        'pending_internal' => ['costing', 'cancelled'],
        'costing' => ['responded', 'cancelled'],
        'responded' => ['converted_to_quote', 'cancelled'],
        'converted_to_quote' => [],
        'cancelled' => [],
    ];

    public const CONTRACT = [
        'draft' => ['negotiation', 'terminated'],
        'negotiation' => ['active', 'draft', 'terminated'],
        'active' => ['expiring_soon', 'renewed', 'terminated', 'expired'],
        'expiring_soon' => ['renewed', 'terminated', 'expired', 'active'],
        'renewed' => ['active', 'expired', 'terminated'],
        'terminated' => [],
        'expired' => [],
    ];

    public const PRICE_LIST = [
        'draft' => ['active'],
        'active' => ['expired', 'draft'],
        'expired' => ['draft'],
    ];

    public const QUOTATION_EDITABLE = ['draft', 'rejected'];

    public const PRICE_REQUEST_EDITABLE = ['new', 'pending_internal', 'costing'];

    public const CONTRACT_EDITABLE = ['draft', 'negotiation'];

    public const PRICE_LIST_EDITABLE = ['draft'];

    public function assertUserTransition(string $document, string $from, string $to, array $context = []): void
    {
        if ($from === $to) {
            return;
        }

        $map = match ($document) {
            'quotation' => self::QUOTATION,
            'price_request' => self::PRICE_REQUEST,
            'contract' => self::CONTRACT,
            'price_list' => self::PRICE_LIST,
            default => [],
        };

        $allowed = $map[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            abort(422, "Invalid {$document} transition from '{$from}' to '{$to}'.");
        }

        if ($document === 'quotation' && $to === 'accepted' && ($context['approval_required'] ?? false) && $from !== 'under_review') {
            abort(422, 'This quotation requires approval and cannot skip under_review.');
        }

        if ($document === 'quotation' && in_array($to, ['converted', 'archived'], true)) {
            abort(422, 'Converted and archived quotation statuses are system-only.');
        }

        if ($document === 'price_request' && $to === 'converted_to_quote') {
            abort(422, 'Price requests are converted to quotations through the quotation workflow, not a free status change.');
        }
    }

    public function assertEditable(string $document, string $status): void
    {
        $allowed = match ($document) {
            'quotation' => self::QUOTATION_EDITABLE,
            'price_request' => self::PRICE_REQUEST_EDITABLE,
            'contract' => self::CONTRACT_EDITABLE,
            'price_list' => self::PRICE_LIST_EDITABLE,
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            abort(422, "This {$document} cannot be edited in status '{$status}'.");
        }
    }

    public function assertDeletable(string $document, string $status): void
    {
        $ok = match ($document) {
            'quotation' => in_array($status, ['draft', 'rejected', 'expired'], true),
            'price_request' => in_array($status, ['new', 'cancelled'], true),
            'contract' => in_array($status, ['draft', 'terminated'], true),
            'price_list' => in_array($status, ['draft', 'expired'], true),
            'sales_order' => in_array($status, ['draft', 'rejected', 'cancelled'], true),
            default => false,
        };

        if (! $ok) {
            abort(422, "This {$document} cannot be deleted in status '{$status}'.");
        }
    }
}
