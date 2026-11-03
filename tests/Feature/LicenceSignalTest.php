<?php

namespace Tests\Feature;

use App\Jobs\RefreshLicenceJob;
use App\Support\Activation\LicenseState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LicenceSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unactivated_install_ignores_the_signal(): void
    {
        Bus::fake();
        $this->postJson('/api/licence-signal')->assertStatus(404);
        Bus::assertNotDispatched(RefreshLicenceJob::class);
    }

    public function test_an_activated_install_queues_one_check_in_and_debounces_repeats(): void
    {
        Bus::fake();
        Cache::forget('licence-signal:debounce');
        LicenseState::storeActivation('https://platform.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        $this->postJson('/api/licence-signal', ['reason' => 'tier_upgraded'])->assertStatus(202);
        $this->postJson('/api/licence-signal')->assertStatus(202);
        Bus::assertDispatchedTimes(RefreshLicenceJob::class, 1);
    }

    public function test_the_request_body_is_never_believed(): void
    {
        Bus::fake();
        Cache::forget('licence-signal:debounce');
        LicenseState::storeActivation('https://platform.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        $this->postJson('/api/licence-signal', ['locks' => [], 'level' => 'full'])->assertStatus(202);
        $this->assertNull(\App\Support\Activation\LicenseState::get('entitlement_signed'), 'a signal carries no entitlement and stores none');
    }
}
