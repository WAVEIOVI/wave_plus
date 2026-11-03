<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ServicingConsumable extends Pivot
{
    use HasUlids;

    protected $table = 'servicing_consumables';
}
