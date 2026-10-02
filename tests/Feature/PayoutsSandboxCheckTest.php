<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutsSandboxCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_without_a_key_and_with_a_live_key(): void
    {
        config(['services.paystack.secret_key' => '']);
        $this->artisan('payouts:sandbox-check')->assertFailed();
        config(['services.paystack.secret_key' => 'sk_live_abc']);
        Http::fake();
        $this->artisan('payouts:sandbox-check')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_read_only_pass_against_a_faked_sandbox(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_abc', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake([
            '*/balance' => Http::response(['status' => true, 'data' => [['currency' => 'NGN', 'balance' => 150000]]]),
            '*/country' => Http::response(['status' => true, 'data' => [['iso_code' => 'NG'], ['iso_code' => 'RW']]]),
            '*/transfer/verify/*' => Http::response(['status' => false, 'message' => 'Transfer not found'], 404),
            '*/bank*' => Http::response(['status' => true, 'data' => [['name' => 'Access Bank', 'code' => '044']]]),
        ]);
        $this->artisan('payouts:sandbox-check')->expectsOutputToContain('NOT IN OURS')->assertSuccessful();
    }
}
