<?php

namespace Tests\Feature;

use App\Services\NCI\NciScorer;
use App\Support\CountryPickerSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Opening the Numbers country/service pickers must be instant: no component round trip, and no per-country database work. */
class NumbersPickerSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pickers_open_from_the_button_without_a_server_round_trip(): void
    {
        foreach (['verify', 'rent', 'line'] as $f) {
            $src = (string) file_get_contents(resource_path("views/partials/numbers-modal/$f.blade.php"));
            $this->assertStringNotContainsString('wire:click="pickCountry"', $src, "$f still re-renders the whole flow to open the country picker");
            $this->assertStringNotContainsString('wire:click="pickService"', $src, "$f still re-renders the whole flow to open the service picker");
            $this->assertStringContainsString("open-country-picker", $src.(string) file_get_contents(resource_path('views/partials/numbers-modal/verify.blade.php')));
        }
    }

    public function test_a_provider_with_too_few_outcomes_is_remembered_so_the_picker_does_not_query_per_country(): void
    {
        Cache::flush();
        $scorer = app(NciScorer::class);
        DB::enableQueryLog();
        $this->assertNull($scorer->publicSuccessRate('fivesim'));
        $first = count(DB::getQueryLog());
        DB::flushQueryLog();
        for ($i = 0; $i < 50; $i++) {
            $this->assertNull($scorer->publicSuccessRate('fivesim'));
        }
        $this->assertGreaterThan(0, $first);
        $this->assertCount(0, DB::getQueryLog(), 'a cached "not enough data" answer must not hit the database again');
    }

    public function test_building_the_numbers_picker_list_stays_within_a_small_query_budget(): void
    {
        config(['services.fivesim.api_key' => 'test-key']);
        Cache::flush();
        DB::enableQueryLog();
        $rows = CountryPickerSources::options('numbers');
        $this->assertGreaterThan(100, count($rows));
        $this->assertLessThan(40, count(DB::getQueryLog()), 'the cold list build must not run queries per country');
    }
}
