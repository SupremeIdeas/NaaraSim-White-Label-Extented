<?php

namespace Tests\Unit;

use App\Support\CountryFlags;
use PHPUnit\Framework\TestCase;

/** Every country the number picker can list must resolve to a flag, including provider spellings and "(Virtual)" lanes. */
class CountryFlagCoverageTest extends TestCase
{
    public function test_provider_slugs_and_labels_resolve_to_a_flag(): void
    {
        foreach (['Afghanistan' => 'af', 'Aland Islands' => 'ax', 'Antigua and Barbuda' => 'ag', 'Armenia' => 'am', 'Australia (Virtual)' => 'au', 'Bosnia' => 'ba', 'Czech Republic' => 'cz', 'Macau' => 'mo', 'Swaziland' => 'sz', 'Salvador' => 'sv', 'East Timor' => 'tl', 'United States (Virtual)' => 'us', 'Saint Vincent and the Grenadines' => 'vc', 'england' => 'gb', 'Zimbabwe' => 'zw', 'Yemen' => 'ye'] as $name => $iso) {
            $this->assertSame($iso, CountryFlags::iso($name), $name);
        }
    }

    public function test_every_flag_icons_country_resolves_by_its_own_name(): void
    {
        $file = dirname(__DIR__, 2).'/node_modules/flag-icons/country.json';
        if (! is_file($file)) {
            $this->markTestSkipped('flag-icons not installed');
        }
        foreach (json_decode((string) file_get_contents($file), true) as $c) {
            if (preg_match('/^[a-z]{2}$/', $c['code'])) {
                $this->assertNotNull(CountryFlags::iso($c['name']), $c['name']);
            }
        }
    }
}
