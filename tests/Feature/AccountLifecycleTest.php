<?php

namespace Tests\Feature;

use App\Jobs\ExportUserDataJob;
use App\Livewire\Account;
use App\Livewire\Admin\AccountDeletions;
use App\Models\EsimOrder;
use App\Models\Referral;
use App\Models\User;
use App\Support\MediaStorage;
use App\Support\UserDataExporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(): User
    {
        $u = User::factory()->create();
        $u->assignRole('user');

        return $u;
    }

    public function test_a_user_can_pause_and_resume_their_account(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(Account::class)->call('deactivate');
        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->deactivated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deactivated']);

        // Paused: other customer routes bounce to the account page…
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('account'));
        // …but the account page itself stays reachable so they can resume.
        $this->actingAs($user)->get(route('account'))->assertOk();

        Livewire::actingAs($user)->test(Account::class)->call('reactivate');
        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->deactivated_at);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.reactivated']);
    }

    public function test_requesting_an_export_queues_the_job(): void
    {
        Queue::fake();
        $user = $this->user();

        Livewire::actingAs($user)->test(Account::class)->call('requestExport');

        Queue::assertPushed(ExportUserDataJob::class, fn ($job) => $job->userId === $user->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.export_requested']);
    }

    public function test_the_export_job_writes_a_private_file_the_owner_can_download(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.wasabi.key' => null]); // force local private disk

        $user = $this->user();
        (new ExportUserDataJob($user->id))->handle();

        $user->refresh();
        $this->assertNotNull($user->data_export_ready_at);
        $this->assertNotNull($user->data_export_path);
        Storage::disk('local')->assertExists($user->data_export_path);

        // The owner can download it; the response is an attachment.
        $this->actingAs($user)->get(route('account.export.download'))
            ->assertOk()
            ->assertDownload('naarasim-data-export.json');
    }

    public function test_the_download_route_404s_when_there_is_no_export(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('account.export.download'))->assertNotFound();
    }

    public function test_the_export_excludes_internal_cost_and_masks_third_party_pii(): void
    {
        $user = $this->user();

        // A private eSIM order with a cost column that must never be exported.
        EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'provider_order_ref' => 'REF-1',
            'iccid' => '8944', 'status' => 'active', 'price_charged' => 10.00,
            'wholesale_cost' => 4.00, 'currency' => 'USD',
        ]);

        // Someone this user referred — a third party whose PII must be masked.
        $referred = User::factory()->create(['name' => 'Zoe Secret', 'email' => 'zoe.secret@example.com']);
        Referral::create([
            'referrer_id' => $user->id, 'referred_id' => $referred->id,
            'reward_pct' => 10, 'rewarded' => true, 'rewarded_at' => now(),
        ]);

        $json = UserDataExporter::toJson($user);

        // Own data present.
        $this->assertStringContainsString('REF-1', $json);
        $this->assertStringContainsString($user->email, $json);
        // Internal cost never leaks.
        $this->assertStringNotContainsString('wholesale_cost', $json);
        $this->assertStringNotContainsString('4.00', $json);
        // Third-party PII never leaks — only an anonymised marker.
        $this->assertStringNotContainsString('zoe.secret@example.com', $json);
        $this->assertStringNotContainsString('Zoe Secret', $json);
        $this->assertStringContainsString('user #'.$referred->id, $json);
    }

    public function test_a_super_admin_approves_a_deletion_and_the_account_is_erased(): void
    {
        $user = $this->user();
        $order = EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'provider_order_ref' => 'REF-2',
            'status' => 'active', 'price_charged' => 5.00, 'wholesale_cost' => 2.00, 'currency' => 'USD',
        ]);

        // User requests deletion.
        Livewire::actingAs($user)->test(Account::class)->call('requestDeletion');
        $user->refresh();
        $this->assertTrue($user->hasPendingDeletion());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deletion_requested']);

        // Super admin approves via the admin queue -> erased.
        $super = User::factory()->create();
        $super->assignRole('super_admin');

        Livewire::actingAs($super)->test(AccountDeletions::class)
            ->assertSee($user->email)
            ->call('approve', $user->id);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('esim_orders', ['id' => $order->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deletion_approved']);
        // Erasure tombstone survives the user row.
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.erased']);
    }

    public function test_a_non_super_admin_cannot_approve_a_deletion(): void
    {
        $user = $this->user();
        Livewire::actingAs($user)->test(Account::class)->call('requestDeletion');

        $admin = User::factory()->create();
        $admin->assignRole('admin'); // not super_admin -> may view, may not approve

        Livewire::actingAs($admin)->test(AccountDeletions::class)
            ->call('approve', $user->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $user->id]); // still here
    }
}
