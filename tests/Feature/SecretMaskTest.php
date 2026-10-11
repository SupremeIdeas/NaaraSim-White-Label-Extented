<?php

namespace Tests\Feature;

use App\Support\MailSettings;
use App\Support\ProviderKeys;
use Tests\TestCase;

/** Engineering blueprint 1F: a saved secret never shows any character of itself, nor its length. */
class SecretMaskTest extends TestCase
{
    public function test_a_saved_provider_secret_shows_only_a_fixed_mask(): void
    {
        $secretFields = [];
        foreach (ProviderKeys::schema() as $group) {
            foreach ($group['fields'] as $name => $meta) {
                if (! empty($meta['secret'])) {
                    $secretFields[$name] = $meta['config'];
                }
            }
        }
        $this->assertNotEmpty($secretFields);

        foreach (array_slice($secretFields, 0, 12, true) as $name => $config) {
            foreach (['sk_live_ABCDEFGH12345678WXYZ', 'abcd'] as $value) {
                config([$config => $value]);
                $this->assertSame(str_repeat('•', 8), ProviderKeys::preview($name), "secret {$name} leaked its shape");
                $this->assertStringNotContainsString(substr($value, -2), (string) ProviderKeys::preview($name));
            }
        }
    }

    public function test_the_smtp_password_preview_never_reveals_a_character_or_the_length(): void
    {
        foreach (['x9', 'correct-horse-battery-staple', 'abcdef'] as $pw) {
            config(['mail.mailers.smtp.password' => $pw]);
            MailSettings::flush();
            $preview = MailSettings::passwordPreview();
            if ($preview === null) {
                $this->markTestSkipped('smtp_password is not mapped to this config path');
            }
            $this->assertSame(str_repeat('•', 8), $preview);
        }
    }
}
