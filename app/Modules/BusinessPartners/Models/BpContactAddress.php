<?php

namespace App\Modules\BusinessPartners\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BpContactAddress extends Pivot
{
    use HasUlids;

    protected $table = 'bp_contact_address';
}
