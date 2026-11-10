<?php

namespace Tests\Feature\Kyc;

use App\Models\KycVerification;
use App\Models\Setting;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Support\KycSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Live probing (2026-11-10) showed Dojah answers 402 when its wallet is empty: that must never read as "could not reach" or reject a customer. */
class DojahOutcomesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');
    }

    private function nin(int $status, array $body, string $name = 'Jane Doe'): KycVerification
    {
        Http::fake(['*/api/v1/kyc/nin*' => Http::response($body, $status)]);

        return app(KycService::class)->submit(User::factory()->create(['name' => $name]), KycVerification::L2, ['id_number' => '12345678901']);
    }

    public function test_an_empty_dojah_wallet_goes_to_manual_review_not_failure(): void
    {
        $v = $this->nin(402, ['error' => 'Your balance is low, pls visit the dashboard to top up']);

        $this->assertSame(KycVerification::PENDING, $v->status);
        $this->assertNotSame('Could not reach Dojah.', $v->reason);
    }

    public function test_a_missing_record_is_rejected(): void
    {
        $this->assertSame(KycVerification::REJECTED, $this->nin(404, ['error' => 'not found'])->status);
        $this->assertSame(KycVerification::REJECTED, $this->nin(400, ['error' => 'bad nin'])->status);
    }

    public function test_bad_credentials_or_a_server_error_fail_without_approving(): void
    {
        $this->assertSame(KycVerification::FAILED, $this->nin(401, ['error' => 'unauthorised'])->status);
        $this->assertSame(KycVerification::FAILED, $this->nin(500, [])->status);
    }

    public function test_a_real_id_for_a_different_name_waits_for_a_human(): void
    {
        $v = $this->nin(200, ['entity' => ['first_name' => 'Chinedu', 'last_name' => 'Okafor']], 'Jane Doe');

        $this->assertSame(KycVerification::PENDING, $v->status);
        $this->assertFalse($v->checks['name_match']);
    }

    public function test_a_matching_name_in_any_order_is_approved(): void
    {
        $v = $this->nin(200, ['entity' => ['first_name' => 'Chinedu', 'last_name' => 'Okafor']], 'Okafor Chinedu Samuel');

        $this->assertSame(KycVerification::APPROVED, $v->status);
    }

    public function test_cac_empty_wallet_goes_to_manual_review(): void
    {
        Http::fake(['*/api/v1/kyc/cac*' => Http::response(['error' => 'Your balance is low'], 402)]);
        $v = app(KycService::class)->submit(User::factory()->create(), KycVerification::L3, ['country' => 'NG', 'id_type' => 'CAC', 'id_number' => 'RC123456']);

        $this->assertSame(KycVerification::PENDING, $v->status);
    }
}
