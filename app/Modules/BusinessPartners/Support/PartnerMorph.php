<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Support;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Canonical polymorphic owner map for Business Partners nested resources.
 *
 * Stored morph types remain full class names (existing production rows).
 * URL slugs are the only public identifier other modules should use.
 */
final class PartnerMorph
{
    public const SLUG_CLIENTS = 'clients';

    public const SLUG_SUPPLIERS = 'suppliers';

    public const SLUG_SERVICE_PROVIDERS = 'service-providers';

    public const SLUG_ORGANISATIONS = 'organisations';

    /**
     * @var array<string, class-string<Model>>
     */
    public const MAP = [
        self::SLUG_CLIENTS => Client::class,
        self::SLUG_SUPPLIERS => Supplier::class,
        self::SLUG_SERVICE_PROVIDERS => ServiceProvider::class,
        self::SLUG_ORGANISATIONS => Organisation::class,
    ];

    /**
     * @return class-string<Model>
     */
    public static function classFor(string $slug): string
    {
        if (! isset(self::MAP[$slug])) {
            abort(404, "Unknown partner type: {$slug}");
        }

        return self::MAP[$slug];
    }

    public static function findOrFail(string $slug, string $id): Model
    {
        $class = self::classFor($slug);

        return $class::query()->findOrFail($id);
    }

    public static function slugFor(Model|string $owner): string
    {
        $class = is_string($owner) ? $owner : $owner::class;

        $slug = array_search($class, self::MAP, true);

        if ($slug === false) {
            throw new InvalidArgumentException("Unknown partner class: {$class}");
        }

        return $slug;
    }

    /**
     * Permission subject used by Spatie / policies (plural resource name).
     */
    public static function permissionSubject(Model|string $owner): string
    {
        $slug = is_string($owner) && isset(self::MAP[$owner])
            ? $owner
            : self::slugFor($owner);

        return $slug;
    }
}
