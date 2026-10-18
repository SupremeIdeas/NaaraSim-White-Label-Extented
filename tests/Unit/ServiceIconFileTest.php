<?php

namespace Tests\Unit;

use App\Support\ServiceIcons;
use Tests\TestCase;

/** Brand marks shipped as files (Simple Icons, exact slug) resolve to an image instead of the letter avatar. */
class ServiceIconFileTest extends TestCase
{
    public function test_a_shipped_brand_mark_resolves_to_an_image_and_unknown_services_still_get_a_letter(): void
    {
        $this->assertGreaterThan(100, count(glob(public_path('images/service-icons/*.svg'))));
        $this->assertSame('img', ServiceIcons::resolve('activision')['type']);
        $this->assertStringEndsWith('/images/service-icons/activision.svg', ServiceIcons::resolve('activision')['url']);
        $this->assertSame('letter', ServiceIcons::resolve('zzz-not-a-brand')['type']);
        $this->assertSame('sprite', ServiceIcons::resolve('whatsapp')['type'], 'the bundled sprite still wins for the core brands');
    }

    public function test_no_shipped_icon_carries_script(): void
    {
        foreach (glob(public_path('images/service-icons/*.svg')) as $f) {
            $b = strtolower((string) file_get_contents($f));
            $this->assertStringNotContainsString('<script', $b, basename($f));
            $this->assertStringNotContainsString('onload', $b, basename($f));
        }
    }
}
