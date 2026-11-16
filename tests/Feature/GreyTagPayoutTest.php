<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\PayoutRails\Grey\GreyAccountService;
use App\PayoutRails\Grey\GreyPayoutGateway;
use App\Services\Payouts\AccountResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Grey Tag payouts: the tag is resolved by Grey, must belong to the member, and is paid by P2P with the usual money rules. */
class GreyTagPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\Payouts\PayoutService::class);
        Cache::flush();
        config(['services.grey.api_secret' => 'gbsk_test', 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co', 'payouts.env' => 'sandbox']);
    }

    private function fake(array $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs + ['*/v1/balances' => Http::response(['status' => 'success', 'data' => ['balances' => [['currency' => 'USD', 'currency_type' => 'fiat', 'available_balance' => 5000, 'pending_balance' => 0]]]])]);
    }

    private function tag(string $first = 'Ada', string $last = 'Obi', int $status = 200, bool $valid = true)
    {
        return Http::response(['status' => 'success', 'data' => ['is_valid' => $valid, 'first_name' => $first, 'last_name' => $last, 'user_name' => '']], $status);
    }

    public function test_a_tag_is_resolved_and_saved_only_when_it_belongs_to_the_member(): void
    {
        $this->fake(['*/v1/grey-tags/*' => $this->tag('Ada', 'Obi')]);
        $user = User::factory()->create(['name' => 'Ada Chioma Obi']);
        $svc = app(GreyAccountService::class);

        $this->assertSame('Ada Obi', $svc->resolveTag('@AdaObi')['name']);
        $a = $svc->addTag($user, '@AdaObi');
        $this->assertSame('grey_tag', $a->method);
        $this->assertSame('USD', $a->currency);
        $this->assertSame('@adaobi', $a->account_number);
        $this->assertSame('Ada Obi', $a->account_name);
        $this->assertSame('grey', $a->provider);
    }

    public function test_someone_elses_tag_unknown_tags_and_bad_input_are_refused(): void
    {
        $user = User::factory()->create(['name' => 'Ada Obi']);
        $svc = app(GreyAccountService::class);

        $this->fake(['*/v1/grey-tags/*' => $this->tag('Tunde', 'Bello')]);
        try { $svc->addTag($user, 'tunde'); $this->fail('should refuse'); } catch (AccountResolutionException $e) { $this->assertStringContainsString('different name', $e->getMessage()); }

        $this->fake(['*/v1/grey-tags/*' => $this->tag('', '', 400, false)]);
        try { $svc->resolveTag('nobody'); $this->fail('should refuse'); } catch (AccountResolutionException $e) { $this->assertStringContainsString('No Grey account', $e->getMessage()); }

        $this->expectException(AccountResolutionException::class);
        $svc->resolveTag('x');
    }

    public function test_the_payout_is_a_p2p_transfer_with_the_idempotent_reference(): void
    {
        $this->fake([
            '*/v1/grey-tags/*' => $this->tag('Ada', 'Obi'),
            '*/v1/charge/p2p' => Http::response(['status' => 'success', 'data' => ['id' => 'x', 'reference' => 'txn_1', 'status' => 'processing']]),
        ]);
        $user = User::factory()->create(['name' => 'Ada Obi']);
        $account = app(GreyAccountService::class)->addTag($user, 'adaobi');
        $request = PayoutRequest::create(['user_id' => $user->id, 'payee_type' => 'referral', 'payout_account_id' => $account->id, 'amount' => 25, 'currency' => 'USD', 'source_bucket' => 'referral', 'status' => 'processing', 'provider' => 'grey', 'reference' => 'PO-grey-tag-1', 'provider_reference' => 'NS-GREY-TAG-1']);

        $gw = app(GreyPayoutGateway::class);
        $this->assertSame('grey-tag:adaobi', $gw->createRecipient($account));
        $res = $gw->sendTransfer($request, $account);

        $this->assertSame('processing', $res->status);
        Http::assertSent(function (HttpRequest $r) use ($request) {
            if (! str_ends_with($r->url(), '/v1/charge/p2p')) {
                return false;
            }
            $b = $r->data();

            return $b['username'] === 'adaobi' && $b['source_currency'] === 'USD' && $b['destination_currency'] === 'USD'
                && (float) $b['source_amount'] === 25.0 && $b['reference'] === $request->wireReference() && $r->header('X-Idempotency-Key') === [$request->wireReference()];
        });
    }
}
