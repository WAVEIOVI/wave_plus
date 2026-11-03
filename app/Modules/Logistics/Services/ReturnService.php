<?php

namespace App\Modules\Logistics\Services;

use App\Modules\Logistics\Events\ReturnValidated;
use App\Modules\Logistics\Models\ReturnOrder;
use Exception;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Validate a return order and update its status.
     *
     * @param  \App\Models\User  $actor
     * @return ReturnOrder
     *
     * @throws Exception
     */
    public function validate(ReturnOrder $return, $actor)
    {
        if ($return->status !== 'draft') {
            throw new Exception('Only draft returns can be validated.');
        }

        return DB::transaction(function () use ($return, $actor) {
            $return->status = 'validated';
            $return->validated_by = $actor->id;
            $return->validated_at = now();
            $return->updated_by = $actor->id;
            $return->save();

            event(new ReturnValidated($return, $actor));

            return $return;
        });
    }
}
