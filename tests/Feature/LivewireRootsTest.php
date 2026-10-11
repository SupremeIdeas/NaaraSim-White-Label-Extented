<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Engineering v3 Phase 3.1 (the "tabs and quick amounts hang until refresh" bug). `<x-nx.page>` starts with a Livewire block marker, so when it is
 * the FIRST thing in a Livewire view Livewire puts `wire:id` on the INNER div. State on the outer div (an Alpine `x-data` tab) then sits outside the
 * component and is wiped by the first update. Every Livewire view must therefore start with a single plain root and put `<x-nx.page>` inside it.
 */
class LivewireRootsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_livewire_view_starts_with_the_page_component(): void
    {
        $bad = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views/livewire')));
        foreach ($it as $file) {
            if (! str_ends_with((string) $file, '.blade.php')) {
                continue;
            }
            $s = (string) preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents((string) $file));
            $s = ltrim((string) preg_replace('/^\s*(@props\(.*?\)|@php.*?@endphp)/s', '', ltrim($s)));
            if (str_starts_with($s, '<x-nx.page')) {
                $bad[] = str_replace(resource_path('views/'), '', (string) $file);
            }
        }
        $this->assertSame([], $bad, 'Wrap <x-nx.page> in a single plain <div> root: '.implode(', ', $bad));
    }

    public function test_the_wallet_component_root_is_the_outer_element_that_holds_the_tab_state(): void
    {
        $this->seed(RoleSeeder::class);
        $html = Livewire::actingAs(User::factory()->create())->test(Wallet::class)->html();

        // The first real element carries the Livewire id, and the Alpine tab state lives INSIDE it.
        $this->assertMatchesRegularExpression('/^\s*(<!--.*?-->\s*)*<div[^>]*wire:id=/s', $html);
        $this->assertGreaterThan(strpos($html, 'wire:id='), strpos($html, "x-data=\"{ tab: 'topup' }\""));
    }
}
