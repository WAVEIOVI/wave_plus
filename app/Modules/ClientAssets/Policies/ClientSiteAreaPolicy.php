<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Policies;

class ClientSiteAreaPolicy extends ClientAssetsPolicy
{
    protected function subject(): string
    {
        return 'client-sites';
    }
}
