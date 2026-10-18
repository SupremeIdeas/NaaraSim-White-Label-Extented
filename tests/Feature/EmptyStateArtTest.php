<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Empty states carry a drawn scene when one is named and the file exists; plain ones stay plain. */
class EmptyStateArtTest extends TestCase
{
    public function test_the_shipped_illustrations_exist(): void
    {
        foreach (['es-wallet', 'es-receipts', 'es-bell', 'es-referral', 'es-bank', 'giftcard'] as $k) {
            $this->assertFileExists(public_path("images/illustrations/$k.svg"));
        }
    }

    public function test_the_empty_component_shows_art_only_when_the_file_exists(): void
    {
        $this->assertStringContainsString('data-empty-art', Blade::render('<x-nx.empty art="es-wallet" title="T" />'));
        $this->assertStringNotContainsString('data-empty-art', Blade::render('<x-nx.empty art="no-such-art" title="T" />'));
        $this->assertStringNotContainsString('data-empty-art', Blade::render('<x-nx.empty title="T" />'));
    }
}
