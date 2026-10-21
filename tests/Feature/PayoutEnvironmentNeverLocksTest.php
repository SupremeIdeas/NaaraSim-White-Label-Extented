<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Payouts\Hardening\PayoutEnvGuard;
use App\Support\PayoutSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutEnvironmentNeverLocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_real_https_website_is_live_even_with_the_old_sandbox_line_in_env(): void
    {
        config(['payouts.env' => 'sandbox', 'app.url' => 'https://www.naara.app']);
        Setting::setValue(PayoutSettings::ENV_MODE, 'auto');   // what the migration writes on an existing install

        $this->assertSame('live', PayoutEnvGuard::mode());
        config(['services.paystack.secret_key' => 'sk_live_abc']);
        $this->assertTrue(PayoutEnvGuard::allows('paystack'));
    }

    public function test_localhost_stays_sandbox_so_a_dev_box_never_moves_real_money(): void
    {
        config(['app.url' => 'http://localhost']);
        Setting::setValue(PayoutSettings::ENV_MODE, 'live');

        $this->assertSame('sandbox', PayoutEnvGuard::mode());
        foreach (['https://127.0.0.1', 'https://naara.test', 'https://192.168.1.5', 'https://myhost'] as $u) {
            config(['app.url' => $u]);
            $this->assertFalse(PayoutEnvGuard::onRealWebsite(), $u);
        }
    }

    public function test_sandbox_only_happens_when_an_admin_chooses_it(): void
    {
        config(['app.url' => 'https://www.naara.app', 'payouts.env' => 'auto']);
        $this->assertSame('live', PayoutEnvGuard::mode());

        Setting::setValue(PayoutSettings::ENV_MODE, 'sandbox');
        $this->assertSame('sandbox', PayoutEnvGuard::mode());

        Setting::setValue(PayoutSettings::ENV_MODE, 'auto');
        $this->assertSame('live', PayoutEnvGuard::mode());
    }

    public function test_the_wrong_class_of_key_is_still_refused_in_either_mode(): void
    {
        config(['app.url' => 'https://www.naara.app', 'services.paystack.secret_key' => 'sk_test_abc']);
        Setting::setValue(PayoutSettings::ENV_MODE, 'auto');
        $this->assertFalse(PayoutEnvGuard::allows('paystack'), 'a test key is never used for real payouts');

        Setting::setValue(PayoutSettings::ENV_MODE, 'sandbox');
        config(['services.paystack.secret_key' => 'sk_live_abc']);
        $this->assertFalse(PayoutEnvGuard::allows('paystack'), 'a live key is never used in sandbox');
    }

    public function test_the_example_env_no_longer_ships_the_sandbox_lock(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('PAYOUT_ENV=auto', $env);
        $this->assertStringNotContainsString('PAYOUT_ENV=sandbox', $env);
    }
}
