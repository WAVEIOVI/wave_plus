<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientSite;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientSiteService
{
    /**
     * Create client site with address validation.
     */
    public function createSite(array $data, ?User $user = null): ClientSite
    {
        $this->validateClientAddress($data);

        $data['created_by'] = $user?->id;
        $data['updated_by'] = $user?->id;

        return DB::transaction(function () use ($data) {
            return ClientSite::create($data);
        });
    }

    /**
     * Update client site with address validation.
     */
    public function updateSite(ClientSite $site, array $data, ?User $user = null): ClientSite
    {
        $this->validateClientAddress($data, $site);

        $newClientId = $data['client_id'] ?? $site->client_id;
        if ($newClientId !== $site->client_id && $site->equipment()->exists()) {
            throw ValidationException::withMessages([
                'client_id' => 'Cannot change the site client while equipment is assigned to this site.',
            ]);
        }

        $data['updated_by'] = $user?->id;

        $site->update($data);

        return $site;
    }

    /**
     * Validate that the selected address actually belongs to the selected client.
     */
    public function validateClientAddress(array $data, ?ClientSite $existing = null): void
    {
        $clientId = $data['client_id'] ?? $existing?->client_id;
        $addressId = $data['bp_address_id'] ?? $existing?->bp_address_id;

        if ($addressId && $clientId) {
            $address = BpAddress::find($addressId);
            if (! $address) {
                throw ValidationException::withMessages([
                    'bp_address_id' => 'The selected address does not exist.',
                ]);
            }

            $isClientAddress = ($address->addressable_type === Client::class || $address->addressable_type === 'client')
                && $address->addressable_id === $clientId;

            if (! $isClientAddress) {
                throw ValidationException::withMessages([
                    'bp_address_id' => 'The selected address does not belong to the chosen client.',
                ]);
            }
        }
    }

    /**
     * Get aggregated KPI meta statistics for sites.
     */
    public function getSitesMeta(): array
    {
        $total = ClientSite::count();
        $active = ClientSite::where('status', 'active')->count();
        $withAreas = ClientSite::has('areas')->count();
        $withEquipment = ClientSite::has('equipment')->count();

        return [
            'total_sites' => $total,
            'active_sites' => $active,
            'with_areas' => $withAreas,
            'with_equipment' => $withEquipment,
        ];
    }
}
