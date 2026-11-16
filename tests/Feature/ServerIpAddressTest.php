<?php

namespace Tests\Feature;

use App\Livewire\Admin\ServerIpAddress;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin > System > Server IP address: hidden until the super admin re-enters their password, rate limited, audited, copyable, re-masks itself. */
class ServerIpAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    private function fakeIp(): void
    {
        Http::fake(['*' => Http::response('203.0.113.7', 200)]);
    }

    private function admin(string $role = 'super_admin'): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true, 'password' => 'Correct-horse-9']);
        $u->assignRole($role);

        return $u;
    }

    public function test_the_address_is_not_in_the_page_until_the_password_is_given(): void
    {
        $this->fakeIp();
        $c = Livewire::actingAs($this->admin())->test(ServerIpAddress::class)->assertSeeHtml('data-ip-masked')->assertDontSee('203.0.113.7')->assertSeeHtml('data-ip-reveal');
        $c->call('ask')->assertSee('Enter your account password')->assertDontSee('203.0.113.7');
        Http::assertNothingSent();
    }

    public function test_a_wrong_password_reveals_nothing_and_five_wrong_ones_lock_it_for_a_while(): void
    {
        $this->fakeIp();
        $u = $this->admin();
        $c = Livewire::actingAs($u)->test(ServerIpAddress::class)->call('ask');
        for ($i = 0; $i < 5; $i++) {
            $c->set('password', 'nope')->call('reveal')->assertDontSee('203.0.113.7')->assertSeeHtml('data-ip-error');
        }
        $c->set('password', 'Correct-horse-9')->call('reveal')->assertSee('Too many attempts')->assertDontSee('203.0.113.7');
        $this->assertSame(0, AuditLog::where('action', 'server_ip.viewed')->count());
    }

    public function test_the_right_password_shows_the_address_with_a_copy_button_and_writes_an_audit_entry(): void
    {
        $this->fakeIp();
        $u = $this->admin();
        Livewire::actingAs($u)->test(ServerIpAddress::class)->call('ask')->set('password', 'Correct-horse-9')->call('reveal')
            ->assertSee('203.0.113.7')->assertSeeHtml('data-ip-copy')->assertSeeHtml('data-ip-value')->assertDontSeeHtml('data-ip-masked')->assertSet('password', '');
        $this->assertSame(1, AuditLog::where('action', 'server_ip.viewed')->where('user_id', $u->id)->count());
    }

    public function test_it_hides_itself_again_after_two_minutes_and_on_demand(): void
    {
        $this->fakeIp();
        $c = Livewire::actingAs($this->admin())->test(ServerIpAddress::class)->call('ask')->set('password', 'Correct-horse-9')->call('reveal')->assertSee('203.0.113.7');
        $this->travel(3)->minutes();
        $c->call('tick')->assertDontSee('203.0.113.7')->assertSeeHtml('data-ip-masked');
        $c->call('ask')->set('password', 'Correct-horse-9')->call('reveal')->assertSee('203.0.113.7')->call('hide')->assertDontSee('203.0.113.7');
    }

    public function test_when_no_server_can_report_it_the_page_says_how_to_find_it(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        Livewire::actingAs($this->admin())->test(ServerIpAddress::class)->call('ask')->set('password', 'Correct-horse-9')->call('reveal')->assertSeeHtml('data-ip-missing')->assertSee('topup:run ip');
    }

    public function test_only_a_super_admin_can_open_it_and_it_is_in_the_system_menu(): void
    {
        $this->fakeIp();
        $path = '/'.config('admin.path', 'adminmaster').'/server-ip';
        $this->actingAs($this->admin('staff'))->get($path)->assertForbidden();
        $this->actingAs($this->admin())->get($path)->assertOk()->assertSee('Server IP address')->assertDontSee('203.0.113.7');
    }
}
