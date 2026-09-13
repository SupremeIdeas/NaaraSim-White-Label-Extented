<?php

namespace Tests\Feature;

use App\Livewire\Admin\PortInRequests;
use App\Livewire\PortIn;
use App\Models\PortInRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * US/Canada port-in intake (Prompt 11): honest intake + status tracking, never
 * instant provisioning. +1-only (the audit boundary); losing-carrier secrets
 * are encrypted and purged once a request closes.
 */
class PortInTest extends TestCase
{
    use RefreshDatabase;

    private function verified(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function fill(\Livewire\Features\SupportTesting\Testable $c): \Livewire\Features\SupportTesting\Testable
    {
        return $c->set('phone_number', '+15550001234')
            ->set('account_number', 'ACC-12345')
            ->set('pin', '9999')
            ->set('billing_name', 'Jane Doe')
            ->set('billing_address', '1 Main St, Springfield');
    }

    public function test_a_customer_can_submit_a_us_canada_port_in_request(): void
    {
        $user = $this->verified();

        $this->fill(Livewire::actingAs($user)->test(PortIn::class))->call('submit');

        $req = PortInRequest::where('user_id', $user->id)->first();
        $this->assertNotNull($req);
        $this->assertSame('+15550001234', $req->phone_number);
        $this->assertSame(PortInRequest::STATUS_SUBMITTED, $req->status);
        // Encrypted cast round-trips on access.
        $this->assertSame('ACC-12345', $req->account_number);
        $this->assertSame('9999', $req->pin);
    }

    public function test_a_non_us_canada_number_is_refused(): void
    {
        $user = $this->verified();

        Livewire::actingAs($user)->test(PortIn::class)
            ->set('phone_number', '+2348012345678')
            ->set('account_number', 'ACC')->set('pin', '1')
            ->set('billing_name', 'X')->set('billing_address', 'Y')
            ->call('submit')
            ->assertHasErrors('phone_number');

        $this->assertSame(0, PortInRequest::count());
    }

    public function test_a_duplicate_open_request_for_the_same_number_is_blocked(): void
    {
        $user = $this->verified();
        PortInRequest::create([
            'user_id' => $user->id, 'phone_number' => '+15550001234',
            'status' => PortInRequest::STATUS_IN_REVIEW,
            'billing_name' => 'Jane', 'billing_address' => 'Addr',
        ]);

        $this->fill(Livewire::actingAs($user)->test(PortIn::class))->call('submit');

        $this->assertSame(1, PortInRequest::where('phone_number', '+15550001234')->count());
    }

    public function test_my_lines_links_to_the_port_in_page(): void
    {
        $this->actingAs($this->verified())->get('/numbers/lines')->assertOk()
            ->assertSee('Bring it to Naara');
    }

    public function test_completing_a_request_purges_the_carrier_secrets(): void
    {
        $user = $this->verified();
        $req = PortInRequest::create([
            'user_id' => $user->id, 'phone_number' => '+15550001234',
            'status' => PortInRequest::STATUS_SUBMITTED_TO_CARRIER,
            'account_number' => 'ACC-12345', 'pin' => '9999',
            'billing_name' => 'Jane', 'billing_address' => 'Addr',
        ]);

        Livewire::actingAs($this->admin())->test(PortInRequests::class)
            ->call('setStatus', $req->id, PortInRequest::STATUS_COMPLETED);

        $req->refresh();
        $this->assertSame(PortInRequest::STATUS_COMPLETED, $req->status);
        $this->assertNull($req->account_number);
        $this->assertNull($req->pin);
    }

    public function test_rejecting_requires_a_reason_and_purges_secrets(): void
    {
        $user = $this->verified();
        $req = PortInRequest::create([
            'user_id' => $user->id, 'phone_number' => '+15550001234',
            'status' => PortInRequest::STATUS_IN_REVIEW,
            'account_number' => 'ACC-12345', 'pin' => '9999',
            'billing_name' => 'Jane', 'billing_address' => 'Addr',
        ]);

        $c = Livewire::actingAs($this->admin())->test(PortInRequests::class);
        // No reason → validation error, nothing changes.
        $c->call('reject', $req->id)->assertHasErrors('rejectReason');
        $this->assertSame(PortInRequest::STATUS_IN_REVIEW, $req->fresh()->status);

        $c->set('rejectReason', 'Account number did not match the carrier record.')
            ->call('reject', $req->id);
        $req->refresh();
        $this->assertSame(PortInRequest::STATUS_REJECTED, $req->status);
        $this->assertSame('Account number did not match the carrier record.', $req->rejection_reason);
        $this->assertNull($req->pin);
    }

    public function test_a_non_admin_cannot_open_the_port_in_admin_screen(): void
    {
        Livewire::actingAs($this->verified())->test(PortInRequests::class)->assertForbidden();
    }
}
