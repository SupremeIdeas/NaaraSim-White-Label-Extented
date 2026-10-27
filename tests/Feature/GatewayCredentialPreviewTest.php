<?php

namespace Tests\Feature;

use App\Support\GatewayCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Hub blueprint section 6: a saved gateway secret never shows a single character of itself. */
class GatewayCredentialPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_secret_key_preview_is_a_fixed_mask(): void
    {
        $secret = 'sk_'.'live_'.'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123';
        GatewayCredentials::saveAll(['paystack' => ['live' => ['secret_key' => $secret, 'public_key' => 'pk_'.'live_'.'PUBLICKEY12345']]]);

        $preview = GatewayCredentials::preview('paystack', 'live', 'secret_key');
        $this->assertSame(str_repeat('•', 8), $preview);
        foreach (str_split($secret, 4) as $slice) {
            $this->assertStringNotContainsString($slice, (string) $preview);
        }
        $this->assertNotNull(GatewayCredentials::preview('paystack', 'live', 'public_key'));
    }
}
