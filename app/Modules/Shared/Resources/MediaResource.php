<?php

declare(strict_types=1);

namespace App\Modules\Shared\Resources;

use App\Modules\Shared\Helpers\MediaFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Media) {
            return MediaFormatter::format($this->resource);
        }

        return (array) $this->resource;
    }
}
