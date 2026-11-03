<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\HasMedia;

class BrandingSyncService
{
    /**
     * Synchronize logo across any brandable entity (Client, Supplier, Organisation,
     * ServiceProvider, CatalogBrand) ensuring exactly one authoritative logo source
     * (uploaded file, external provider URL, or initials).
     */
    public static function syncLogo(Model $record, ?Request $request): void
    {
        if (! $record instanceof HasMedia || ! $request) {
            return;
        }

        // 1. Uploaded file takes highest precedence
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $meta = MediaValidationService::validateAndProcess($file, MediaValidationService::CATEGORY_IMAGE, 'logo');

            $record->clearMediaCollection('logo');
            $record->addMedia($file)
                ->usingFileName($meta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $meta['uploaded_by'],
                    'uploaded_by_id' => $meta['uploaded_by_id'],
                    'width' => $meta['width'],
                    'height' => $meta['height'],
                ])
                ->toMediaCollection('logo', 'public');

            $record->forceFill([
                'logo_source' => 'upload',
                'logo_domain' => null,
                'logo_external_url' => null,
                'logo_path' => null,
            ])->save();

            return;
        }

        // 2. Explicit logo_source update (e.g. switching to brandfetch, logo_dev, or initials)
        if (! $request->exists('logo_source')) {
            return;
        }

        $source = $request->input('logo_source');
        if (! in_array($source, ['brandfetch', 'logo_dev', 'initials', 'upload'], true)) {
            return;
        }

        if ($source === 'initials') {
            $record->clearMediaCollection('logo');
            $record->forceFill([
                'logo_source' => 'initials',
                'logo_domain' => null,
                'logo_external_url' => null,
                'logo_path' => null,
            ])->save();

            return;
        }

        if ($source === 'brandfetch' || $source === 'logo_dev') {
            $record->clearMediaCollection('logo');
            $domain = $request->input('logo_domain');
            $externalUrl = $request->input('logo_external_url');

            // Sanitize / stabilize provider URL if needed
            $cleanUrl = static::stabilizeProviderUrl($externalUrl, $source, $domain);

            $record->forceFill([
                'logo_source' => $source,
                'logo_domain' => $domain,
                'logo_external_url' => $cleanUrl,
                'logo_path' => null,
            ])->save();
        }
    }

    /**
     * Ensure provider URLs use stable client parameters or CDN formats rather than
     * expiring temporary session tokens.
     */
    public static function stabilizeProviderUrl(?string $url, string $source = 'brandfetch', ?string $domain = null): ?string
    {
        if (! $url) {
            return null;
        }

        // Auto-detect source if default was passed
        if (str_contains($url, 'logo.dev')) {
            $source = 'logo_dev';
        } elseif (str_contains($url, 'brandfetch')) {
            $source = 'brandfetch';
        }

        // If Logo.dev, ensure public key token is present if configured
        if ($source === 'logo_dev') {
            $publicKey = (string) config('services.logo_dev.public_key');
            if ($publicKey) {
                if ($domain && ! str_contains($url, '://')) {
                    return 'https://img.logo.dev/'.rawurlencode(strtolower(trim($domain))).'?size=512&format=png&token='.rawurlencode($publicKey);
                }
                if (str_contains($url, 'logo.dev') && ! str_contains($url, 'token=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';

                    return $url.$separator.'token='.rawurlencode($publicKey);
                }
            }
        }

        // If Brandfetch, replace ephemeral session token (?c=1ax... or &c=...) or append client ID
        if ($source === 'brandfetch') {
            $clientId = (string) config('services.brandfetch.client_id');
            if ($clientId && (str_contains($url, 'cdn.brandfetch.io') || str_contains($url, 'asset.brandfetch.io'))) {
                if (preg_match('/([?&])c=[^&#]*/', $url)) {
                    return preg_replace('/([?&])c=[^&#]*/', '$1c='.rawurlencode($clientId), $url);
                }

                $separator = str_contains($url, '?') ? '&' : '?';

                return $url.$separator.'c='.rawurlencode($clientId);
            }
        }

        return $url;
    }
}
