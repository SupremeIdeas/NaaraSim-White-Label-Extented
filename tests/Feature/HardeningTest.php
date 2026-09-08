<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\ErrorLog;
use App\Models\OrderLog;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Support\Auditor;
use App\Support\ErrorLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_model_exposes_cost_or_profit_in_serialized_output(): void
    {
        $user = User::factory()->create();

        $plan = EsimPlan::create(['provider' => 'esimgo', 'provider_plan_id' => 'h1', 'name' => 'P', 'cost_price_usd' => 3.3, 'airalo_min_price' => 2.0, 'markup_pct' => 25, 'computed_retail_usd' => 5]);
        $order = EsimOrder::create(['user_id' => $user->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 5, 'wholesale_cost' => 3.3]);
        $sms = SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'status' => 'completed', 'provider_cost' => 0.2, 'charged_to_user' => 0.5, 'profit' => 0.3]);
        $vn = VirtualNumber::create(['user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+1555', 'monthly_cost' => 1, 'monthly_retail' => 3]);
        $log = OrderLog::create(['user_id' => $user->id, 'provider' => 'esimgo', 'provider_cost' => 3.3, 'charged_to_user' => 5, 'profit' => 1.7]);

        foreach ([
            [$plan, ['cost_price_usd', 'airalo_min_price', 'markup_pct']],
            [$order, ['wholesale_cost']],
            [$sms, ['provider_cost', 'profit']],
            [$vn, ['monthly_cost']],
            [$log, ['provider_cost', 'profit']],
        ] as [$model, $secret]) {
            $payload = $model->toArray();
            foreach ($secret as $field) {
                $this->assertArrayNotHasKey($field, $payload, $model::class." leaked {$field}");
            }
        }
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $this->get('/faq')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_server_errors_are_captured_to_the_error_log(): void
    {
        ErrorLogger::capture(new RuntimeException('kaboom in a job'));

        $this->assertDatabaseHas('error_logs', ['message' => 'kaboom in a job', 'severity' => 'error']);
    }

    public function test_expected_http_and_validation_exceptions_are_not_logged_as_errors(): void
    {
        ErrorLogger::capture(new NotFoundHttpException('nope'));
        ErrorLogger::capture(ValidationException::withMessages(['x' => 'bad']));

        $this->assertSame(0, ErrorLog::count());
    }

    public function test_the_report_hook_writes_a_thrown_exception_to_the_error_log(): void
    {
        Route::get('/_boom_test', fn () => throw new RuntimeException('boom via http'));

        $this->get('/_boom_test')->assertStatus(500);

        $this->assertDatabaseHas('error_logs', ['message' => 'boom via http']);
    }

    public function test_order_actions_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $plan = EsimPlan::create(['provider' => 'esimgo', 'provider_plan_id' => 'r1', 'name' => 'R', 'cost_price_usd' => 3, 'computed_retail_usd' => 10])->fresh();

        // Exhaust the 10/min order budget for this user.
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('orders:'.$user->id, 60);
        }

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true) // pass the device gate to reach the rate limiter
            ->call('purchase')
            ->assertSet('error', 'Too many orders in a short time. Please wait a minute and try again.');

        // Nothing was charged/ordered.
        $this->assertSame(0, EsimOrder::count());
    }

    public function test_auditor_records_who_did_what(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Auditor::log('test.action', EsimPlan::class, 42, ['k' => 'v']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id, 'action' => 'test.action', 'model_id' => 42,
        ]);
    }
}
