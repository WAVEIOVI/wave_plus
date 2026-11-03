<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

/**
 * Commercial Sales Order lifecycle statuses.
 *
 * Canonical flow: draft → submitted → under_review → accepted → rejected | cancelled | completed | closed
 *
 * The statuses marked @deprecated below represent operational or legacy states that
 * do NOT belong in the commercial SO lifecycle and should NOT be used for new transitions.
 * They are retained only for backward DB compatibility.
 */
enum SalesOrderStatus: string
{
    // ── Canonical commercial lifecycle ──────────────────────────────────────
    case Draft       = 'draft';
    case Submitted   = 'submitted';
    case UnderReview = 'under_review';
    case Accepted    = 'accepted';       // Commercially approved; commitment doc generated
    case Rejected    = 'rejected';
    case Cancelled   = 'cancelled';
    case Completed   = 'completed';
    case Closed      = 'closed';

    // ── Legacy / deprecated operational aliases ─────────────────────────────
    // These belong to downstream DWO/SWO operational documents, not the commercial SO.

    /** @deprecated Operational status — use Accepted. Do NOT set on new SO transitions. */
    case Dispatched = 'dispatched';

    /** @deprecated Operational status — use Accepted. Do NOT set on new SO transitions. */
    case InFulfillment = 'in_fulfillment';

    /** @deprecated Operational status — use Accepted. Do NOT set on new SO transitions. */
    case Confirmed = 'confirmed';

    /** @deprecated Operational status. Do NOT set on new SO transitions. */
    case InExecution = 'in_execution';

    /** @deprecated Operational status. Do NOT set on new SO transitions. */
    case PartiallyFulfilled = 'partially_fulfilled';

    /** @deprecated Operational status. Do NOT set on new SO transitions. */
    case Fulfilled = 'fulfilled';

    /** @deprecated Finance status. Do NOT set on new SO transitions. */
    case Invoiced = 'invoiced';
}
