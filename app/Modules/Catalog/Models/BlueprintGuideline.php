<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BlueprintGuideline extends Pivot
{
    use HasUlids;

    protected $table = 'blueprint_guideline';
}
