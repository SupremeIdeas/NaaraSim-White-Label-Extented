<?php

namespace Tests\Feature;

use App\Services\eSIM\AiraloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiraloCataloguePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_walks_every_page(): void
    {
        config(['services.airalo.client_id' => 'id', 'services.airalo.client_secret' => 'sec']);
        Cache::flush();
        Http::fake([
            '*/token' => Http::response(['data' => ['access_token' => 'tok', 'expires_in' => 3600]]),
            '*/packages*' => Http::sequence()
                ->push(['data' => [['slug' => 'a']], 'meta' => ['last_page' => 2]])
                ->push(['data' => [['slug' => 'b']], 'meta' => ['last_page' => 2]]),
        ]);

        $out = app(AiraloService::class)->getCatalogue();

        $this->assertSame(['a', 'b'], array_column($out, 'slug'));
    }
}
