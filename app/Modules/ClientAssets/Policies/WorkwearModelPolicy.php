<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Policies;

class WorkwearModelPolicy extends ClientAssetsPolicy
{
    protected function subject(): string
    {
        return 'workwear-models';
    }
}
