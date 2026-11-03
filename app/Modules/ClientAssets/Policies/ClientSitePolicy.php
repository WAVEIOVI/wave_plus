<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Policies;

class ClientSitePolicy extends ClientAssetsPolicy
{
    protected function subject(): string
    {
        return 'client-sites';
    }
}
