<?php

namespace Tests\Feature;

use App\Livewire\SendMessage;
use App\Livewire\SupportChat;
use App\Models\User;
use App\Models\VirtualNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** Chat Composer Pro (<naara-composer>): the one composer for every chat/message surface. */
class ChatComposerTest extends TestCase
{
    use RefreshDatabase;

    private function line(array $attrs): VirtualNumber
    {
        return VirtualNumber::create($attrs + ['sid' => 'PN'.uniqid(), 'monthly_cost' => 1, 'monthly_retail' => 2, 'auto_renew' => true]);
    }

    private function config(string $html): array
    {
        $this->assertMatchesRegularExpression('/<naara-composer[^>]*data-config="([^"]*)"/', $html);
        preg_match('/<naara-composer[^>]*data-config="([^"]*)"/', $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_the_component_renders_the_element_with_translated_labels_and_wire_ignore(): void
    {
        app()->setLocale('fr');
        $html = Blade::render('<x-composer convo-id="c1" :features="[\'emoji\']" />');

        $this->assertStringContainsString('wire:ignore', $html);
        $cfg = $this->config($html);
        $this->assertSame('chat', $cfg['mode']);
        $this->assertSame('fr', $cfg['lang']);
        $this->assertSame('Envoyer', $cfg['labels']['send']);
        $this->assertSame(['emoji'], $cfg['features']);
        $this->assertSame('c1', $cfg['convoId']);
    }

    public function test_every_locale_ships_the_same_label_keys(): void
    {
        $en = array_keys(require lang_path('en/composer.php'));
        foreach (['fr', 'sw', 'ar'] as $l) {
            $this->assertSame([], array_diff($en, array_keys(require lang_path("$l/composer.php"))), "$l is missing composer labels");
        }
    }

    public function test_support_chat_uses_the_composer_with_only_what_the_server_accepts(): void
    {
        $html = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)->html();
        $cfg = $this->config($html);

        $this->assertSame(['emoji', 'attach', 'voice'], $cfg['features']);           // no gif / schedule / location / contact / poll
        $this->assertSame(['media', 'document'], $cfg['attachKinds']);
        $this->assertSame(1, $cfg['maxFiles']);                                      // SupportAttachment: one evidence file
        $this->assertSame('application/pdf', $cfg['accept']['document']);
        $this->assertStringContainsString('image/png', $cfg['accept']['media']);
        $this->assertTrue($cfg['bare']);
        $this->assertStringContainsString('needs microphone access', $cfg['labels']['micPrimer']);
        // Livewire glue: answers cc:transmit with the existing pipeline — no second transport.
        $this->assertStringContainsString('cc:transmit', $html);
        $this->assertStringContainsString('$wire.send()', $html);
        $this->assertStringContainsString('$wire.sendVoice()', $html);
        $this->assertStringNotContainsString('voiceRecorder()', $html);
    }

    public function test_the_rollout_flag_restores_the_previous_support_composer(): void
    {
        config(['composer.surfaces.support_chat' => false]);
        $html = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)->html();

        $this->assertStringNotContainsString('<naara-composer', $html);
        $this->assertStringContainsString('voiceRecorder()', $html);
    }

    public function test_the_naara_line_modal_uses_the_composer_in_field_mode_and_keeps_its_paid_send_button(): void
    {
        $user = User::factory()->create();
        $this->line(['user_id' => $user->id, 'status' => 'active', 'phone_number' => '+12025550143', 'provider' => 'twilio', 'capabilities' => ['sms' => true, 'mms' => true]]);

        $html = Livewire::actingAs($user)->test(SendMessage::class)->call('openFor', '+12025550199', 'Ada')->html();
        $cfg = $this->config($html);

        $this->assertSame('field', $cfg['mode']);
        $this->assertSame(918, $cfg['maxChars']);
        $this->assertSame(['emoji', 'attach', 'voice'], $cfg['features']);
        $this->assertSame('image/jpeg,image/png,image/gif', $cfg['accept']['media']);
        $this->assertStringContainsString('wire:click="send"', $html);   // the explicit, wallet-charging button is still the only way to send
    }

    public function test_the_naara_line_composer_offers_no_attachments_on_a_line_that_cannot_send_mms(): void
    {
        $user = User::factory()->create();
        $this->line(['user_id' => $user->id, 'status' => 'active', 'phone_number' => '+2348012345678', 'provider' => 'twilio', 'capabilities' => ['sms' => true]]);

        $cfg = $this->config(Livewire::actingAs($user)->test(SendMessage::class)->call('openFor', '+12025550199', 'Ada')->html());

        $this->assertSame(['emoji'], $cfg['features']);
    }

    public function test_the_composer_sources_have_no_blocking_dialogs_demo_mode_or_emoji_chrome(): void
    {
        foreach (glob(resource_path('js/composer/*.js')) as $file) {
            // Strip comments so prose that mentions prompt()/confirm() is not mistaken for a call.
            $src = preg_replace(['#/\*.*?\*/#s', '#(?<!:)//[^\n]*#'], '', file_get_contents($file));
            $name = basename($file);
            $this->assertDoesNotMatchRegularExpression('/(?<![\w.])(alert|prompt|confirm)\(/', $src, "$name uses a blocking dialog");
            $this->assertStringNotContainsString('console.log', $src, "$name logs");
            $this->assertStringNotContainsString('demo mode', strtolower($src), "$name has a demo mode");
        }
        // The chrome is SVG; the only emoji glyphs allowed are in the shared emoji dataset.
        foreach (['icons.js', 'labels.js', 'index.js'] as $f) {
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', file_get_contents(resource_path('js/composer/'.$f)), "$f contains emoji");
        }
    }

    public function test_the_csp_allows_blob_previews_for_attachments(): void
    {
        $this->assertStringContainsString('blob:', collect(explode(';', config('security.csp.policy')))->first(fn ($d) => str_contains($d, 'img-src')));
    }

    public function test_the_ui_kit_shows_the_composer_with_every_feature(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $html = Livewire::actingAs($admin)->test(\App\Livewire\Admin\UiKit::class)->html();
        $cfg = $this->config($html);

        $this->assertContains('gif', $cfg['features']);
        $this->assertContains('schedule', $cfg['features']);
        $this->assertContains('poll', $cfg['attachKinds']);
    }

    public function test_the_permissions_policy_lets_the_page_use_the_microphone_for_voice_notes(): void
    {
        // With `microphone=()` every in-browser voice recording is blocked by the browser before the permission prompt ever appears.
        $this->get('/login')->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(self), camera=()');
    }
}
