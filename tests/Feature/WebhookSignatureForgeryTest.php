<?php

namespace Tests\Feature;

use App\Services\Kyc\DojahKycProvider;
use App\Services\Kyc\SmileIdKycProvider;
use App\Services\Payouts\PaystackPayoutGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Security pentest finding (2026-09-16): three webhook verifiers computed
 * hash_hmac() against an unconfigured (blank) provider secret without first
 * checking the secret was non-empty. `hash_hmac($algo, $body, '')` is
 * publicly computable by anyone — an attacker who knows/guesses the target
 * reference could forge a signature that validates while the provider is
 * simply unconfigured (a real, plausible pre-launch state this platform
 * explicitly supports elsewhere — e.g. FeatureFlags::configured()'s
 * "blank keys show Coming Soon" pattern). Confirmed exploitable against:
 *   - PaystackPayoutGateway: forge a "paid"/"failed" payout webhook.
 *   - Dojah/SmileID KycProvider: forge a KYC "approved" decision on the
 *     attacker's own (real) verification reference, bypassing identity
 *     checks entirely.
 * The sibling `PaystackGateway` (payments, not payout) already had this
 * exact guard with a comment explaining why — these three never got it.
 */
class WebhookSignatureForgeryTest extends TestCase
{
    public function test_paystack_payout_webhook_forgery_rejected_when_unconfigured(): void
    {
        config(['services.paystack.secret_key' => '']);
        $gateway = app(PaystackPayoutGateway::class);

        $body = json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'PO-1', 'status' => 'success']]);
        $forgedSignature = hash_hmac('sha512', $body, ''); // what an attacker computes themselves

        $request = Request::create('/x', 'POST', server: ['HTTP_X_PAYSTACK_SIGNATURE' => $forgedSignature], content: $body);

        $this->assertFalse($gateway->verifyWebhook($request));
    }

    public function test_paystack_payout_webhook_still_verifies_when_configured(): void
    {
        config(['services.paystack.secret_key' => 'real-secret']);
        $gateway = app(PaystackPayoutGateway::class);

        $body = json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'PO-1', 'status' => 'success']]);
        $signature = hash_hmac('sha512', $body, 'real-secret');

        $request = Request::create('/x', 'POST', server: ['HTTP_X_PAYSTACK_SIGNATURE' => $signature], content: $body);

        $this->assertTrue($gateway->verifyWebhook($request));
    }

    public function test_dojah_kyc_webhook_forgery_rejected_when_unconfigured(): void
    {
        config(['services.dojah.api_key' => '']);
        $provider = app(DojahKycProvider::class);

        $body = json_encode(['reference' => 'KYC-1', 'status' => 'Verified']);
        $forgedSignature = hash_hmac('sha256', $body, '');

        $request = Request::create('/x', 'POST', server: ['HTTP_X_DOJAH_SIGNATURE' => $forgedSignature], content: $body);

        $this->assertFalse($provider->verifyWebhook($request));
    }

    public function test_smileid_kyc_webhook_forgery_rejected_when_unconfigured(): void
    {
        config(['services.smileid.api_key' => '']);
        $provider = app(SmileIdKycProvider::class);

        $body = json_encode(['job_id' => 'KYC-1', 'ResultCode' => '0810']);
        $forgedSignature = hash_hmac('sha256', $body, '');

        $request = Request::create('/x', 'POST', server: ['HTTP_X_SMILEID_SIGNATURE' => $forgedSignature], content: $body);

        $this->assertFalse($provider->verifyWebhook($request));
    }
}
