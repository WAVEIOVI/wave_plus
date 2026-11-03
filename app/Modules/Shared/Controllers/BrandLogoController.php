<?php

namespace App\Modules\Shared\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BrandLogoController extends Controller
{
    protected string $brandfetchApiKey;
    protected string $brandfetchClientId;
    protected string $logoDevPublicKey;
    protected string $logoDevSecretKey;
    protected int $cacheTtl = 86400; // 24 hours

    public function __construct()
    {
        // Read from config only: env() returns null once the config is cached.
        $this->brandfetchApiKey = (string) config('services.brandfetch.api_key');
        $this->brandfetchClientId = (string) config('services.brandfetch.client_id');
        $this->logoDevPublicKey = (string) config('services.logo_dev.public_key');
        $this->logoDevSecretKey = (string) config('services.logo_dev.secret_key');
    }

    /**
     * Resolve logo by domain using Brandfetch Logo API
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'force' => 'nullable|boolean',
        ]);

        $domain = $this->normalizeDomain($request->input('domain'));
        $cacheKey = "brand-logo:{$domain}";

        if ($request->boolean('force') || $request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        // Cache the payload array (not a Response object) so every cache driver
        // round-trips plain data and we never cache an HTTP response.
        $result = Cache::remember($cacheKey, $this->cacheTtl, function () use ($domain) {
            return $this->resolveViaBrandfetch($domain)
                ?? $this->resolveViaLogoDev($domain);
        });


        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'No logo found for this domain',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Search brands through Logo.dev when browser-side Brandfetch search has no matches.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
        ]);

        $query = $request->input('q');

        try {
            $results = $this->searchViaLogoDev($query);
        } catch (\Throwable $e) {
            \Log::warning('Logo.dev search failed', ['query' => $query, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Brand search provider is unavailable',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get Brandfetch client ID for frontend Logo Link widget
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'brandfetch_client_id' => $this->brandfetchClientId,
            'logo_dev_public_key' => $this->logoDevPublicKey,
        ]);
    }

    /**
     * Normalize domain (remove protocol, www, paths)
     */
    protected function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);
        $domain = parse_url($domain, PHP_URL_HOST) ?? $domain;
        $domain = rtrim($domain, '/');
        return strtolower($domain);
    }

    /**
     * Resolve via Brandfetch Logo API
     */
    protected function resolveViaBrandfetch(string $domain): ?array
    {
        if (!$this->brandfetchApiKey) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->brandfetchApiKey}",
                    'Accept' => 'application/json',
                ])
                ->get("https://api.brandfetch.io/v2/brands/{$domain}");

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            $url = $this->selectBestLogoUrl($data['logos'] ?? []);

            if (! $url) {
                return null;
            }

            // Stabilize Brandfetch URL with persistent client_id if available
            $url = \App\Modules\Shared\Services\BrandingSyncService::stabilizeProviderUrl($url, 'brandfetch', $domain);

            return [
                'url' => $url,
                'source' => 'brandfetch',
                'domain' => $domain,
                'name' => $data['name'] ?? null,
                'colors' => $data['colors'] ?? [],
            ];
        } catch (\Exception $e) {
            \Log::warning('Brandfetch resolve failed', ['domain' => $domain, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Resolve via Logo.dev API.
     * Uses Logo.dev CDN with public key token for fast and reliable resolution.
     */
    protected function resolveViaLogoDev(string $domain): ?array
    {
        if (! $this->logoDevPublicKey) {
            return null;
        }

        $cdnUrl = 'https://img.logo.dev/' . rawurlencode($domain) . '?size=512&format=png&token=' . rawurlencode($this->logoDevPublicKey);

        return [
            'url' => $cdnUrl,
            'source' => 'logo_dev',
            'domain' => $domain,
            'name' => null,
        ];
    }


    /**
     * Search through Logo.dev when the browser-side Brandfetch search has no matches.
     */
    protected function searchViaLogoDev(string $query): array
    {
        if (! $this->logoDevSecretKey) {
            throw new \RuntimeException('Logo.dev secret key is not configured');
        }

        $response = Http::timeout(10)
            ->withToken($this->logoDevSecretKey)
            ->acceptJson()
            ->get('https://api.logo.dev/v2/search', [
                'q' => $query,
                'method' => 'match',
                'limit' => 25,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Logo.dev returned HTTP {$response->status()}");
        }

        return collect($response->json('data', []))
            ->filter(fn (array $brand) => filled($brand['domain'] ?? null) && filled($brand['logo_url'] ?? null))
            ->map(fn (array $brand) => [
                'domain' => $brand['domain'],
                'name' => $brand['name'] ?? $brand['domain'],
                'url' => \App\Modules\Shared\Services\BrandingSyncService::stabilizeProviderUrl($brand['logo_url'], 'logo_dev', $brand['domain']),
                'source' => 'logo_dev',
            ])
            ->values()
            ->all();
    }

    /**
     * Pick the best hosted logo URL from a Brandfetch "logos" array.
     *
     * Brandfetch returns several logo entries, each with several formats. We
     * prefer SVG (crisp at any size), then the largest PNG/JPEG, rather than
     * blindly trusting the first entry.
     */
    protected function selectBestLogoUrl(array $logos): ?string
    {
        $candidates = [];

        foreach ($logos as $logo) {
            foreach ($logo['formats'] ?? [] as $format) {
                $src = $format['src'] ?? null;

                if (! $src) {
                    continue;
                }

                $type = strtolower($format['format'] ?? '');

                if (! in_array($type, ['svg', 'png', 'jpeg', 'jpg', 'webp'], true)) {
                    continue;
                }

                $candidates[] = [
                    'src' => $src,
                    'type' => $type,
                    'width' => (int) ($format['width'] ?? 0),
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        $priority = ['svg' => 0, 'webp' => 1, 'png' => 2, 'jpeg' => 3, 'jpg' => 3];

        usort($candidates, function (array $a, array $b) use ($priority) {
            $byType = $priority[$a['type']] <=> $priority[$b['type']];

            return $byType !== 0 ? $byType : $b['width'] <=> $a['width'];
        });

        return $candidates[0]['src'];
    }
}