<?php

namespace App\Modules\BusinessPartners\Services;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\BpContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BpContactAddressService
{
    /**
     * Link a contact to an address.
     *
     * CRITICAL RULE: Both the contact and address must share the exact same
     * polymorphic owner (contactable_type == addressable_type AND
     * contactable_id == addressable_id). Cross-partner linking is rejected.
     */
    public function link(BpContact $contact, BpAddress $address, bool $isPrimaryLocation = false): void
    {
        $this->guardSameOwner($contact, $address);

        DB::transaction(function () use ($contact, $address, $isPrimaryLocation) {
            if ($isPrimaryLocation) {
                DB::table('bp_contact_address')
                    ->where('bp_contact_id', $contact->id)
                    ->update(['is_primary_location' => false]);
            }

            $contact->addresses()->syncWithoutDetaching([
                $address->id => ['is_primary_location' => $isPrimaryLocation],
            ]);
        });
    }

    /**
     * Unlink a contact from an address.
     */
    public function unlink(BpContact $contact, BpAddress $address): void
    {
        $contact->addresses()->detach($address->id);
    }

    /**
     * Update the pivot data (e.g., is_primary_location).
     */
    public function updatePivot(BpContact $contact, BpAddress $address, array $pivotData): void
    {
        $this->guardSameOwner($contact, $address);

        $contact->addresses()->updateExistingPivot($address->id, $pivotData);
    }

    /**
     * Ensure contact and address belong to the same polymorphic owner.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function guardSameOwner(BpContact $contact, BpAddress $address): void
    {
        if (
            $contact->contactable_type !== $address->addressable_type ||
            $contact->contactable_id !== $address->addressable_id
        ) {
            throw ValidationException::withMessages([
                'bp_address_id' => 'The contact and address must belong to the same business partner. Cross-partner linking is not allowed.',
            ]);
        }
    }
}
