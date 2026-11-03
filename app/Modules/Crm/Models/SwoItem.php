<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\OperationalHub\Models\SwoItem as BaseSwoItem;

/**
 * Backward compatibility subclass for CRM module.
 * The primary SwoItem model now resides in App\Modules\OperationalHub\Models\SwoItem.
 */
class SwoItem extends BaseSwoItem
{
}
