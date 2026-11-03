<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Services;

use App\Modules\BusinessPartners\Models\BpAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AddressManagementService
{
    public function createForOwner(Model $owner, array $data): BpAddress
    {
        return DB::transaction(function () use ($owner, $data) {
            $data['addressable_type'] = $owner::class;
            $data['addressable_id'] = $owner->getKey();

            if (! isset($data['is_primary']) && ! $owner->addresses()->exists()) {
                $data['is_primary'] = true;
            }

            $address = BpAddress::query()->create($data);
            $this->syncOwnerHeadquarters($owner);

            return $address->fresh();
        });
    }

    public function update(BpAddress $address, array $data): BpAddress
    {
        return DB::transaction(function () use ($address, $data) {
            $address->update($data);
            $address->refresh();

            if ($address->addressable) {
                $this->syncOwnerHeadquarters($address->addressable);
            }

            return $address;
        });
    }

    public function delete(BpAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $owner = $address->addressable;
            $address->delete();

            if ($owner) {
                $this->syncOwnerHeadquarters($owner);
            }
        });
    }

    /**
     * HQ columns on partner tables are a synchronized snapshot of the owner's main address.
     * Synchronized from the address with is_primary = true, or type = 'headquarters', or first address.
     */
    public function syncOwnerHeadquarters(Model $owner, ?BpAddress $address = null): void
    {
        /** @var BpAddress|null $mainAddress */
        $mainAddress = $owner->addresses()
            ->where('is_primary', true)
            ->first()
            ?? $owner->addresses()->where('type', 'headquarters')->first()
            ?? $owner->addresses()->first();

        $payload = [];

        if (in_array('hq_address', $owner->getFillable(), true)) {
            $payload['hq_address'] = $mainAddress ? $mainAddress->getTranslations('address_street') : null;
        }
        if (in_array('hq_city', $owner->getFillable(), true)) {
            $payload['hq_city'] = $mainAddress ? $mainAddress->getTranslations('city') : null;
        }
        if (in_array('hq_postcode', $owner->getFillable(), true)) {
            $payload['hq_postcode'] = $mainAddress?->postcode;
        }
        if (in_array('hq_country', $owner->getFillable(), true)) {
            $payload['hq_country'] = $mainAddress ? $mainAddress->getTranslations('country') : null;
        }

        if ($payload !== []) {
            $owner->forceFill($payload)->saveQuietly();
        }
    }
}
