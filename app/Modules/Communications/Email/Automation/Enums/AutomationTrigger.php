<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Enums;

enum AutomationTrigger: string
{
    case QuotationSent = 'quotation.sent';
    case QuotationPending = 'quotation.pending';
    case PurchaseOrderSubmitted = 'purchase_order.submitted';
    case InvoiceOverdue = 'invoice.overdue';
    case MissionScheduled = 'mission.scheduled';
    case EquipmentMaintenanceDue = 'equipment.maintenance_due';

    public function label(): string
    {
        return match ($this) {
            self::QuotationSent => 'Quotation Sent',
            self::QuotationPending => 'Quotation Pending',
            self::PurchaseOrderSubmitted => 'Purchase Order Submitted',
            self::InvoiceOverdue => 'Invoice Overdue',
            self::MissionScheduled => 'Mission Scheduled',
            self::EquipmentMaintenanceDue => 'Equipment Maintenance Due',
        };
    }
}
