<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Policies;

class WorkwearEmployeePolicy extends ClientAssetsPolicy
{
    protected function subject(): string
    {
        return 'workwear-employees';
    }
}
