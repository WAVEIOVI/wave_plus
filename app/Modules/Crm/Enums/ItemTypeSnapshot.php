<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum ItemTypeSnapshot: string
{
    case PhysicalProduct = 'physical_product';
    case Service = 'service';
    case SparePart = 'spare_part';
    case Product = 'product';
    case Consumable = 'consumable';
    case EqServicing = 'eq_servicing';
}
