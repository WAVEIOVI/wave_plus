<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Policies;

class ClientEquipmentPolicy extends ClientAssetsPolicy
{
    protected function subject(): string
    {
        return 'client-equipment';
    }
}
