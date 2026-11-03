<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\OperationalHub\Models\ServiceWorkOrder as BaseServiceWorkOrder;

/**
 * Backward compatibility subclass for CRM module.
 * The primary ServiceWorkOrder model now resides in App\Modules\OperationalHub\Models\ServiceWorkOrder.
 */
class ServiceWorkOrder extends BaseServiceWorkOrder
{
}
