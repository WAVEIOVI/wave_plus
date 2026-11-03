<?php

namespace App\Modules\BusinessPartners\Traits;

use App\Modules\Shared\Services\DocumentRenderService;
use Spatie\Browsershot\Browsershot;

trait GeneratesPdf
{
    /**
     * Create a pre-configured Browsershot instance using the shared DocumentRenderService.
     */
    protected function makeBrowsershot(string $html, ?string $orientation = null): Browsershot
    {
        return app(DocumentRenderService::class)->makeBrowsershot($html, $orientation);
    }
}
