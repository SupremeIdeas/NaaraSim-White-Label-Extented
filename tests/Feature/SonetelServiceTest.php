<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Services\SMS\SonetelService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sonetel (owner audit, 2026-09-15) — verified against Sonetel's own public
 * api-docs repo (github.com/Sonetel/sonetel-api-docs). The adapter shipped
 * before the audit assumed a static Bearer API key; Sonetel is actually
 * OAuth2 password-grant (Basic-auth'd client credentials + account
 * username/password -> access_token), and the search/buy/release endpoint
 * paths were all generic REST guesses, not the real resource paths.
 */
class SonetelServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.sonetel.base_url' => 'https://public-api.sonetel.com',
            'services.sonetel.auth_url' => 'https://api.sonetel.com/SonetelAuth/oauth/token',
            'services.sonetel.username' => 'owner@example.com',
            'services.sonetel.password' => 'secret',
            'services.sonetel.account_id' => 'acc-1',
        ]);
    }

    public function test_it_reports_unavailable_until_configured(): void
    {
        config(['services.sonetel.username' => null, 'services.sonetel.password' => null]);
        Http::fake();

        try {
            app(SonetelService::class)->searchNumbers('nigeria');
            $this->fail('Expected SmsException.');
        } catch (SmsException) {
            // expected
        }
        Http::assertNothingSent();
    }

    public function test_it_exchanges_the_account_login_for_a_bearer_token_via_oauth2_password_grant(): void
    {
        Http::fake([
            'api.sonetel.com/SonetelAuth/oauth/token' => Http::response(['access_token' => 'tok-abc', 'token_type' => 'bearer']),
            'public-api.sonetel.com/numberstocksummary/*' => Http::response(['response' => []]),
        ]);

        app(SonetelService::class)->searchNumbers('nigeria');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'SonetelAuth/oauth/token')
            && $r['grant_type'] === 'password'
            && $r['username'] === 'owner@example.com'
            && $r['password'] === 'secret'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('sonetel-api:sonetel-api')));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'numberstocksummary')
            && $r->hasHeader('Authorization', 'Bearer tok-abc'));
    }

    public function test_the_token_is_cached_across_calls_not_refetched_every_time(): void
    {
        Http::fake([
            'api.sonetel.com/SonetelAuth/oauth/token' => Http::response(['access_token' => 'tok-abc']),
            'public-api.sonetel.com/*' => Http::response(['response' => []]),
        ]);

        app(SonetelService::class)->searchNumbers('nigeria');
        app(SonetelService::class)->searchNumbers('ghana');

        Http::assertSentCount(3); // 1 token exchange + 2 searches, not 2 token exchanges
    }

    public function test_a_401_forces_a_fresh_token_and_retries_once(): void
    {
        Http::fake([
            'api.sonetel.com/SonetelAuth/oauth/token' => Http::sequence()
                ->push(['access_token' => 'stale-tok'])
                ->push(['access_token' => 'fresh-tok']),
            'public-api.sonetel.com/*' => Http::sequence()
                ->push(['message' => 'expired'], 401)
                ->push(['response' => []], 200),
        ]);

        $result = app(SonetelService::class)->searchNumbers('nigeria');

        $this->assertSame([], $result);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'numberstocksummary') && $r->hasHeader('Authorization', 'Bearer fresh-tok'));
    }

    public function test_search_numbers_hits_the_real_numberstocksummary_endpoint(): void
    {
        Http::fake([
            'api.sonetel.com/SonetelAuth/oauth/token' => Http::response(['access_token' => 'tok-abc']),
            'public-api.sonetel.com/numberstocksummary/nigeria/availablephonenumber' => Http::response([
                'response' => [['phnum' => '2348000000000', 'city' => 'Lagos', 'monthly_fee' => 1.5]],
            ]),
        ]);

        $numbers = app(SonetelService::class)->searchNumbers('nigeria');

        $this->assertSame('2348000000000', $numbers[0]['number']);
        $this->assertSame('Lagos', $numbers[0]['locality']);
        $this->assertSame(1.5, $numbers[0]['monthly_cost']);
    }

    public function test_buy_number_posts_to_the_account_scoped_subscription_resource(): void
    {
        Http::fake([
            'api.sonetel.com/SonetelAuth/oauth/token' => Http::response(['access_token' => 'tok-abc']),
            'public-api.sonetel.com/account/acc-1/phonenumbersubscription' => Http::response(['response' => ['phnum' => '2348000000000']]),
        ]);

        $out = app(SonetelService::class)->buyNumber('nigeria', ['number' => '+2348000000000']);

        $this->assertSame('+2348000000000', $out['number']);
        $this->assertSame(['sms' => false, 'voice' => true], $out['capabilities']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'account/acc-1/phonenumbersubscription')
            && $r->method() === 'POST' && $r['phnum'] === '2348000000000'); // E.164 without the +
    }

    public function test_release_number_deletes_the_account_scoped_subscription(): void
    {
        Http::fake([
            'api.sonetel.com/SonetelAuth/oauth/token' => Http::response(['access_token' => 'tok-abc']),
            'public-api.sonetel.com/account/acc-1/phonenumbersubscription/2348000000000' => Http::response([]),
        ]);

        app(SonetelService::class)->releaseNumber('2348000000000');

        Http::assertSent(fn ($r) => $r->method() === 'DELETE'
            && str_contains($r->url(), 'account/acc-1/phonenumbersubscription/2348000000000'));
    }

    public function test_send_sms_is_never_offered(): void
    {
        Http::fake();

        try {
            app(SonetelService::class)->sendSms('+234', '+235', 'hi');
            $this->fail('Expected SmsException.');
        } catch (SmsException) {
            // expected — confirmed against the full Sonetel spec repo: no
            // send-SMS resource exists at all, only inbound-SMS-routing.
        }
        Http::assertNothingSent();
    }
}
