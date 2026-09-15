<?php

namespace Tests\Feature;

use App\Livewire\Admin\AppBuilder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Security pentest finding (2026-09-16): App\Livewire\Admin\AppBuilder::
 * fetchOfflineUrl() was the one place in the codebase that fetched an
 * admin-supplied URL with no SSRF check at all, despite App\Rules\PublicUrl /
 * App\Support\Security\SsrfGuard existing specifically for this scenario
 * ("any time the server fetches a URL that could be influenced by user
 * input, run it through here first"). An admin (or a compromised admin
 * session) could have pointed it at a cloud metadata endpoint or an internal
 * service and had the raw response stored as the app's offline page.
 */
class SsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_offline_url_fetch_rejects_a_private_ip_literal(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->set('offlineUrl', 'http://169.254.169.254/latest/meta-data/')
            ->call('fetchOfflineUrl')
            ->assertHasErrors(['offlineUrl']);
    }

    public function test_offline_url_fetch_rejects_loopback(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->set('offlineUrl', 'http://127.0.0.1/admin/secrets')
            ->call('fetchOfflineUrl')
            ->assertHasErrors(['offlineUrl']);
    }

    public function test_offline_url_fetch_rejects_non_http_scheme(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->set('offlineUrl', 'file:///etc/passwd')
            ->call('fetchOfflineUrl')
            ->assertHasErrors(['offlineUrl']);
    }
}
