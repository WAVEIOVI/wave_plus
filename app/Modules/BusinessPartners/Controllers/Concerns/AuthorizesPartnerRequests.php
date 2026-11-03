<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Controllers\Concerns;

use App\Modules\BusinessPartners\Support\PartnerMorph;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesPartnerRequests
{
    use AuthorizesRequests;

    protected function partnerOwner(string $ownerType, string $ownerId): Model
    {
        return PartnerMorph::findOrFail($ownerType, $ownerId);
    }

    protected function morphClass(string $ownerType): string
    {
        return PartnerMorph::classFor($ownerType);
    }

    protected function authorizePartnerView(string $ownerType, string $ownerId): Model
    {
        $owner = $this->partnerOwner($ownerType, $ownerId);
        $this->authorize('view', $owner);

        return $owner;
    }

    protected function authorizePartnerUpdate(string $ownerType, string $ownerId): Model
    {
        $owner = $this->partnerOwner($ownerType, $ownerId);
        $this->authorize('update', $owner);

        return $owner;
    }

    protected function authorizeAddressable(Model $record, string $ability = 'view'): Model
    {
        $owner = $record->addressable
            ?? $record->contactable
            ?? $record->bankable
            ?? $record->certifiable
            ?? $record->documentable;

        abort_unless($owner, 404);
        $this->authorize($ability, $owner);

        return $owner;
    }
}
