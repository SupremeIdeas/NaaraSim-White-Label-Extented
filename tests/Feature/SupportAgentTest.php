<?php

namespace Tests\Feature;

use App\Livewire\Admin\SupportAgent as SupportAgentPage;
use App\Livewire\SupportChat;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\Contracts\ChatModel;
use App\Services\Support\NaaraCareAgent;
use App\Services\Support\SupportTools;
use App\Support\SupportGuard;
use App\Support\SupportReplyGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeChatModel;
use Tests\TestCase;

/**
 * Module 24 — NaaraCare AI support agent.
 */
class SupportAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function conversation(User $user): SupportConversation
    {
        return SupportConversation::create(['user_id' => $user->id, 'title' => 't']);
    }

    public function test_guard_scrubs_cost_and_secret_keys_recursively(): void
    {
        $dirty = [
            'plan' => 'USA 3GB',
            'cost_price_usd' => 3.3,
            'nested' => ['profit' => 9, 'status' => 'active', 'api_key' => 'sk_x'],
        ];
        $clean = SupportGuard::scrub($dirty);

        $this->assertSame('USA 3GB', $clean['plan']);
        $this->assertArrayNotHasKey('cost_price_usd', $clean);
        $this->assertArrayNotHasKey('profit', $clean['nested']);
        $this->assertArrayNotHasKey('api_key', $clean['nested']);
        $this->assertSame('active', $clean['nested']['status']);
    }

    public function test_tools_only_return_the_bound_users_own_orders(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $plan = EsimPlan::create(['provider' => 'esimgo', 'provider_plan_id' => 'p', 'name' => 'Mine', 'cost_price_usd' => 3, 'computed_retail_usd' => 9]);

        EsimOrder::create(['user_id' => $me->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3]);
        EsimOrder::create(['user_id' => $other->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3]);

        $tools = new SupportTools($me);
        $out = $tools->execute('get_my_orders', []);

        $this->assertCount(1, $out['esim_orders']);
        // And no cost/profit leaked through.
        $this->assertArrayNotHasKey('wholesale_cost', $out['esim_orders'][0]);
    }

    public function test_agent_runs_a_tool_then_answers(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);

        $fake = new FakeChatModel([
            FakeChatModel::toolUse('t1', 'check_device_compatibility', ['device' => 'iPhone 14 Pro']),
            FakeChatModel::text('Good news — your iPhone 14 Pro supports eSIM. Want me to take you to the plans?'),
        ]);
        $agent = new NaaraCareAgent($fake);

        $result = $agent->respond($user, $conv, 'Does my iPhone 14 Pro work?');

        $this->assertStringContainsString('supports eSIM', $result['reply']);
        // Second call must have carried the tool_result back to the model.
        $lastMessages = end($fake->calls);
        $this->assertSame('user', $lastMessages[count($lastMessages) - 1]['role']);
        $this->assertSame('tool_result', $lastMessages[count($lastMessages) - 1]['content'][0]['type']);
    }

    public function test_agent_can_escalate_to_a_human(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);

        $fake = new FakeChatModel([
            FakeChatModel::toolUse('t1', 'escalate_to_human', ['reason' => 'Refund request for a failed order']),
            FakeChatModel::text('I have asked a human colleague to help with your refund.'),
        ]);

        $result = (new NaaraCareAgent($fake))->respond($user, $conv, 'I need a refund');

        $this->assertTrue($result['escalated']);
        $this->assertTrue($conv->fresh()->escalated);
    }

    public function test_reply_guard_passes_through_a_normal_reply(): void
    {
        $reply = SupportReplyGuard::sanitize('Your iPhone 14 Pro supports eSIM.', false);
        $this->assertSame('Your iPhone 14 Pro supports eSIM.', $reply);
    }

    public function test_reply_guard_blocks_an_unverified_refund_claim(): void
    {
        $reply = SupportReplyGuard::sanitize("I've refunded you $10 for the failed order.", false);
        $this->assertStringNotContainsString('refunded', $reply);
        $this->assertStringContainsString('human', $reply);
    }

    public function test_reply_guard_allows_the_claim_when_the_action_actually_succeeded(): void
    {
        $reply = SupportReplyGuard::sanitize('Your goodwill credit has been applied — sorry for the trouble!', true);
        $this->assertStringContainsString('goodwill credit has been applied', $reply);
    }

    public function test_agent_escalates_and_rewrites_a_hallucinated_refund_claim(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);

        // The model claims a refund in plain text without ever calling a tool
        // that actually moves money — the guard must catch this on the way out.
        $fake = new FakeChatModel([
            FakeChatModel::text("I've refunded you $10 for the failed order."),
        ]);

        $result = (new NaaraCareAgent($fake))->respond($user, $conv, 'My order failed, can I get my money back?');

        $this->assertStringNotContainsString('refunded', $result['reply']);
        $this->assertTrue($result['escalated']);
    }

    public function test_navigation_tool_surfaces_a_link(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);

        $fake = new FakeChatModel([
            FakeChatModel::toolUse('t1', 'suggest_navigation', ['page' => 'wallet']),
            FakeChatModel::text('You can top up here.'),
        ]);

        $result = (new NaaraCareAgent($fake))->respond($user, $conv, 'How do I add money?');
        $this->assertSame('/wallet', $result['nav']);
    }

    public function test_chat_ui_persists_turns_with_a_fake_model(): void
    {
        $this->app->instance(ChatModel::class, new FakeChatModel([FakeChatModel::text('Hello, how can I help?')]));

        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportChat::class)
            ->set('draft', 'Hi there')
            ->call('send')
            ->assertSee('Hello, how can I help?');

        $this->assertDatabaseHas('support_messages', ['role' => 'user', 'body' => 'Hi there']);
        $this->assertDatabaseHas('support_messages', ['role' => 'assistant', 'body' => 'Hello, how can I help?']);
    }

    public function test_chat_falls_back_gracefully_when_the_model_is_unconfigured(): void
    {
        $this->app->instance(ChatModel::class, new FakeChatModel([], available: false));
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportChat::class)
            ->set('draft', 'Hello?')
            ->call('send')
            ->assertSee('not available');

        $this->assertDatabaseHas('support_messages', ['role' => 'user', 'body' => 'Hello?']);
    }

    public function test_admin_support_agent_page_saves_persona(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        Livewire::actingAs($admin)->test(SupportAgentPage::class)
            ->set('agent_name', 'Amara')
            ->set('persona', 'Warm and concise.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Amara', \App\Support\SupportSettings::name());
    }
}
