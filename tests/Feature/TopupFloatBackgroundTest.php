<?php

namespace Tests\Feature;

use App\Jobs\Topup\PollSupplierFloatJob;
use App\Models\TopupProvider;
use App\Services\Topup\TopupOps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

/** `topup:run float` runs in the background: one queued job per supplier with keys, each recording its own balance or error. */
class TopupFloatBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private function fakes(): array
    {
        $ok = new FakeTopupProvider('zendit');
        $ok->float = 250;
        $bad = new class('ding') extends FakeTopupProvider {
            public function balance(): array
            {
                throw new \RuntimeException('HTTP request returned status code 401');
            }
        };
        foreach (['vtpass', 'reloadly', 'bitrefill', 'paga'] as $c) {
            $this->app->instance('topup.provider.'.$c, new FakeTopupProvider($c, configured: false));
        }
        $this->app->instance('topup.provider.zendit', $ok);
        $this->app->instance('topup.provider.ding', $bad);

        return [$ok, $bad];
    }

    public function test_the_command_queues_one_job_per_supplier_with_keys_and_runs_nothing_itself(): void
    {
        Bus::fake();
        $this->fakes();
        $this->artisan('topup:run float')->expectsOutputToContain('queued for 2 supplier')->assertSuccessful();
        Bus::assertDispatched(PollSupplierFloatJob::class, fn ($j) => $j->code === 'zendit');
        Bus::assertDispatched(PollSupplierFloatJob::class, fn ($j) => $j->code === 'ding');
        Bus::assertNotDispatched(PollSupplierFloatJob::class, fn ($j) => $j->code === 'paga');
        $this->assertNull(TopupProvider::where('code', 'zendit')->value('last_balance'), 'queued, not run inline');
    }

    public function test_a_job_stores_the_balance_and_a_failing_supplier_records_its_error_without_throwing(): void
    {
        $this->fakes();
        (new PollSupplierFloatJob('zendit'))->handle(app(TopupOps::class));
        (new PollSupplierFloatJob('ding'))->handle(app(TopupOps::class));

        $this->assertEquals(250.0, (float) TopupProvider::where('code', 'zendit')->value('last_balance'));
        $this->assertNull(TopupProvider::where('code', 'zendit')->value('last_error'));
        $this->assertStringContainsString('401', (string) TopupProvider::where('code', 'ding')->value('last_error'));
    }

    public function test_now_still_runs_it_here_and_prints_the_result(): void
    {
        $this->fakes();
        $this->artisan('topup:run float --now')->expectsOutputToContain('ding: ERROR')->assertSuccessful();
    }
}
