<?php

namespace Tests\Feature;

use App\Support\Niche\SupportLinks;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The support email on the Contact page is editable, so each install sets its own without touching code. */
class SupportEmailEditableTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_changes_the_address_and_a_broken_value_falls_back(): void
    {
        config(['naara.support.email' => 'base@example.com']);
        $this->assertSame('base@example.com', SupportLinks::email());
        SiteContent::saveOverrides('contact', ['channels' => ['email_address' => 'help@brand.test']]);
        SiteContent::flush('contact');
        $this->assertSame('help@brand.test', SupportLinks::email());
        $this->get('/contact')->assertOk()->assertSee('mailto:help@brand.test', false);
        SiteContent::saveOverrides('contact', ['channels' => ['email_address' => 'nope']]);
        SiteContent::flush('contact');
        $this->assertSame('base@example.com', SupportLinks::email());
    }
}
