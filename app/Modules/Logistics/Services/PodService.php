<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Models\User;
use App\Modules\Logistics\Enums\DeliveryCondition;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\ProofOfDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PodService
{
    /**
     * Record or update Proof of Delivery for a DWO.
     */
    public function recordPod(DeliveryWorkOrder $dwo, array $data, User $actor): ProofOfDelivery
    {
        if (empty($data['receiver_name'])) {
            throw ValidationException::withMessages([
                'receiver_name' => ['Receiver name is required for Proof of Delivery.'],
            ]);
        }

        return DB::transaction(function () use ($dwo, $data, $actor) {
            $condition = isset($data['condition_overall'])
                ? (DeliveryCondition::tryFrom($data['condition_overall']) ?? DeliveryCondition::Good)
                : DeliveryCondition::Good;

            $pod = ProofOfDelivery::updateOrCreate(
                ['dwo_id' => $dwo->id],
                [
                    'delivered_at'      => $data['delivered_at'] ?? now(),
                    'receiver_name'     => $data['receiver_name'],
                    'receiver_title'    => $data['receiver_title'] ?? null,
                    'signature_data'    => $data['signature_data'] ?? null,
                    'signed_at'         => !empty($data['signature_data']) ? ($data['signed_at'] ?? now()) : null,
                    'condition_overall' => $condition,
                    'notes'             => $data['notes'] ?? null,
                    'latitude'          => $data['latitude'] ?? null,
                    'longitude'         => $data['longitude'] ?? null,
                    'created_by'        => $dwo->pod?->created_by ?? $actor->id,
                    'updated_by'        => $actor->id,
                ]
            );

            return $pod;
        });
    }
}
