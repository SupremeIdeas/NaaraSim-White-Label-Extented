<?php

namespace Tests\Feature;

use App\Jobs\ExportUserDataJob;
use App\Livewire\Account;
use App\Livewire\Admin\AccountDeletions;
use App\Models\EsimOrder;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\AccountLifecycleNotification;
use App\Support\MediaStorage;
use App\Support\UserDataExporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        Notification::fake();
        $user = $this->user();

        Livewire::actingAs($user)->test(Account::class)->call('deactivate');
        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->deactivated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deactivated']);
        Notification::assertSentTo($user, AccountLifecycleNotification::class, fn ($n) => $n->action === AccountLifecycleNotification::DEACTIVATED);

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
        Notification::assertSentTo($user, AccountLifecycleNotification::class, fn ($n) => $n->action === AccountLifecycleNotification::REACTIVATED);
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

    public function test_a_super_admin_approves_a_deletion_and_the_account_is_anonymized_not_hard_deleted(): void
    {
        Notification::fake();
        $user = $this->user();
        $originalEmail = $user->email;
        $order = EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'provider_order_ref' => 'REF-2',
            'status' => 'active', 'price_charged' => 5.00, 'wholesale_cost' => 2.00, 'currency' => 'USD',
        ]);

        // User requests deletion.
        Livewire::actingAs($user)->test(Account::class)->call('requestDeletion');
        $user->refresh();
        $this->assertTrue($user->hasPendingDeletion());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deletion_requested']);
        Notification::assertSentTo($user, AccountLifecycleNotification::class, fn ($n) => $n->action === AccountLifecycleNotification::DELETION_REQUESTED);

        // Super admin approves via the admin queue -> anonymized, NOT erased.
        $super = User::factory()->create();
        $super->assignRole('super_admin');

        Livewire::actingAs($super)->test(AccountDeletions::class)
            ->assertSee($user->email)
            ->call('approve', $user->id);

        $user->refresh();

        // The user row survives, but every PII field is wiped.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNotSame($originalEmail, $user->email);
        $this->assertSame('Deleted User', $user->name);
        $this->assertNull($user->phone);
        $this->assertNull($user->date_of_birth);
        $this->assertFalse((bool) $user->is_active);
        $this->assertNotNull($user->anonymized_at);
        $this->assertNotNull($user->retention_purge_due_at);
        $this->assertTrue($user->retention_purge_due_at->isFuture());

        // The financial/order trail is fully retained under the same user id.
        $this->assertDatabaseHas('esim_orders', ['id' => $order->id, 'user_id' => $user->id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deletion_approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.anonymized']);
        // No hard-delete tombstone is written at approval time anymore.
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account.erased']);
        // Sent BEFORE the PII fields were overwritten (notifyNow) so the real
        // name/email still render in the notification, not the placeholder.
        Notification::assertSentTo($user, AccountLifecycleNotification::class, fn ($n) => $n->action === AccountLifecycleNotification::ERASED);
    }

    public function test_the_scheduled_purge_command_leaves_an_account_within_its_retention_window_untouched(): void
    {
        $user = $this->user();
        $service = app(\App\Services\Account\AccountService::class);
        $service->erase($user);
        $user->refresh();
        $this->assertTrue($user->retention_purge_due_at->isFuture());

        $this->artisan('account:purge-erased')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_the_scheduled_purge_command_permanently_deletes_an_account_past_its_retention_window(): void
    {
        $user = $this->user();
        $order = EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'provider_order_ref' => 'REF-3',
            'status' => 'active', 'price_charged' => 5.00, 'wholesale_cost' => 2.00, 'currency' => 'USD',
        ]);

        $service = app(\App\Services\Account\AccountService::class);
        $service->erase($user);
        $user->forceFill(['retention_purge_due_at' => now()->subDay()])->save();

        $this->artisan('account:purge-erased')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('esim_orders', ['id' => $order->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.purged']);
    }

    public function test_a_super_admin_can_view_retained_records_under_a_legal_hold_with_a_case_reference_and_reason(): void
    {
        $user = $this->user();
        EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'provider_order_ref' => 'REF-4',
            'status' => 'active', 'price_charged' => 5.00, 'wholesale_cost' => 2.00, 'currency' => 'USD',
        ]);

        $service = app(\App\Services\Account\AccountService::class);
        $service->erase($user);
        $user->refresh();

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        $view = $service->viewRetainedRecordsForLegalHold($user, $super, 'CASE-123', 'AML audit request');

        $this->assertSame($user->id, $view['user_id']);
        $this->assertCount(1, $view['esim_orders']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.legal_hold_viewed',
            'model_id' => $user->id,
        ]);
    }

    public function test_a_legal_hold_view_requires_a_case_reference_and_reason(): void
    {
        $user = $this->user();
        $service = app(\App\Services\Account\AccountService::class);
        $service->erase($user);
        $user->refresh();

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->viewRetainedRecordsForLegalHold($user, $super, '', '');
    }

    public function test_a_non_super_admin_cannot_view_retained_records_under_legal_hold(): void
    {
        $user = $this->user();
        $service = app(\App\Services\Account\AccountService::class);
        $service->erase($user);
        $user->refresh();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->viewRetainedRecordsForLegalHold($user, $admin, 'CASE-1', 'reason');
    }

    public function test_a_user_can_cancel_a_pending_deletion_request(): void
    {
        Notification::fake();
        $user = $this->user();

        Livewire::actingAs($user)->test(Account::class)->call('requestDeletion');
        Livewire::actingAs($user)->test(Account::class)->call('cancelDeletion');

        $user->refresh();
        $this->assertFalse($user->hasPendingDeletion());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deletion_cancelled']);
        Notification::assertSentTo($user, AccountLifecycleNotification::class, fn ($n) => $n->action === AccountLifecycleNotification::DELETION_CANCELLED);
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
