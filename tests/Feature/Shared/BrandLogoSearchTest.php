<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandLogoSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_dev_search_returns_selectable_brand_results(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.logo_dev.secret_key' => 'test-logo-dev-secret']);

        Http::fake([
            'https://api.logo.dev/v2/search*' => Http::response([
                'data' => [
                    [
                        'name' => '3M',
                        'domain' => '3m.com',
                        'logo_url' => 'https://img.logo.dev/3m.com?token=public-key',
                    ],
                ],
                'metadata' => ['request_id' => 'test-request'],
            ]),
        ]);

        $this->getJson('/api/brand-logo/search?q=3m')
            ->assertOk()
            ->assertJsonPath('data.0.name', '3M')
            ->assertJsonPath('data.0.domain', '3m.com')
            ->assertJsonPath('data.0.url', 'https://img.logo.dev/3m.com?token=public-key')
            ->assertJsonPath('data.0.source', 'logo_dev');

        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://api.logo.dev/v2/search?')
            && $request->hasHeader('Authorization', 'Bearer test-logo-dev-secret'));
    }
}
