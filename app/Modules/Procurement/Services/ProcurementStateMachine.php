<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

/**
 * Canonical WAVE PLUS procurement state machine.
 * User PATCH /status may only follow these graphs. System receipt/award
 * updates use dedicated methods and never invent extra user-facing statuses.
 */
class ProcurementStateMachine
{
    public const IPR = [
        'draft' => ['pending_approval', 'cancelled'],
        'pending_approval' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['cancelled'],
        'rejected' => ['draft', 'cancelled'],
        'merged' => [],
        'converted_to_po' => [],
        'cancelled' => [],
    ];

    public const RFQ = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['awaiting_responses', 'cancelled'],
        'awaiting_responses' => ['under_evaluation', 'cancelled'],
        'under_evaluation' => ['awarded', 'cancelled'],
        'awarded' => [],
        'cancelled' => [],
    ];

    public const PO = [
        'draft' => ['pending_approval', 'approved', 'cancelled'],
        'pending_approval' => ['approved', 'cancelled'],
        'approved' => ['sent', 'cancelled'],
        'sent' => ['acknowledged', 'in_delivery', 'cancelled'],
        'acknowledged' => ['in_delivery', 'cancelled'],
        'in_delivery' => ['cancelled'],
        'partially_received' => ['closed'],
        'fully_received' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public const GRN = [
        'draft' => ['under_qc'],
        'under_qc' => ['confirmed', 'partially_rejected', 'fully_rejected'],
        'partially_rejected' => ['confirmed', 'fully_rejected'],
        'confirmed' => [],
        'fully_rejected' => [],
        'stocked' => [],
    ];

    public const PO_RECEIVABLE = ['sent', 'acknowledged', 'in_delivery', 'partially_received'];

    public const IPR_EDITABLE = ['draft', 'rejected'];

    public const RFQ_EDITABLE = ['draft'];

    public const PO_EDITABLE = ['draft', 'pending_approval'];

    public const GRN_EDITABLE = ['draft', 'under_qc', 'partially_rejected'];

    public function assertUserTransition(string $document, string $from, string $to, array $context = []): void
    {
        if ($from === $to) {
            return;
        }

        $map = match ($document) {
            'ipr' => self::IPR,
            'rfq' => self::RFQ,
            'po' => self::PO,
            'grn' => self::GRN,
            default => [],
        };

        $allowed = $map[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            abort(422, "Invalid {$document} transition from '{$from}' to '{$to}'.");
        }

        if ($document === 'po' && $from === 'draft' && $to === 'approved' && ($context['approval_required'] ?? false)) {
            abort(422, 'This purchase order requires approval and cannot skip pending_approval.');
        }

        if ($document === 'po' && in_array($from, ['sent', 'acknowledged', 'in_delivery'], true) && $to === 'cancelled' && ($context['has_receipts'] ?? false)) {
            abort(422, 'A purchase order with goods receipts cannot be cancelled.');
        }
    }

    public function assertEditable(string $document, string $status): void
    {
        $allowed = match ($document) {
            'ipr' => self::IPR_EDITABLE,
            'rfq' => self::RFQ_EDITABLE,
            'po' => self::PO_EDITABLE,
            'grn' => self::GRN_EDITABLE,
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            abort(422, "This {$document} cannot be edited in status '{$status}'.");
        }
    }

    public function assertDeletable(string $document, string $status, array $context = []): void
    {
        $ok = match ($document) {
            'ipr' => in_array($status, ['draft', 'cancelled'], true),
            'rfq' => in_array($status, ['draft', 'cancelled'], true),
            'po' => in_array($status, ['draft', 'cancelled'], true) && empty($context['has_receipts']),
            'grn' => in_array($status, ['draft', 'under_qc', 'partially_rejected', 'fully_rejected'], true),
            default => false,
        };

        if (! $ok) {
            abort(422, "This {$document} cannot be deleted in status '{$status}'.");
        }
    }

    public function assertPoReceivable(string $status): void
    {
        if (! in_array($status, self::PO_RECEIVABLE, true)) {
            abort(422, "Goods cannot be received against a purchase order in status '{$status}'.");
        }
    }

    public function assertRfqAcceptsBids(string $status): void
    {
        if (! in_array($status, ['sent', 'awaiting_responses', 'under_evaluation'], true)) {
            abort(422, "Supplier bids cannot be recorded while the RFQ is '{$status}'.");
        }
    }
}
