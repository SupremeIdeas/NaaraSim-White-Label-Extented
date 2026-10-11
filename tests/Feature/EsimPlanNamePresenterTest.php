<?php

namespace Tests\Feature;

use App\Support\EsimCatalogue;
use PHPUnit\Framework\TestCase;

class EsimPlanNamePresenterTest extends TestCase
{
    public function test_provider_strings_become_plain_destinations(): void
    {
        $this->assertSame('Aaland Islands', EsimCatalogue::cleanPlanName('eSIM, 1GB, 7 Days, Aaland Islands, V2'));
        $this->assertSame('United Kingdom', EsimCatalogue::cleanPlanName('eSIM, 0.5GB, 3 Days, United Kingdom'));
        $this->assertSame('Europe, Turkey', EsimCatalogue::cleanPlanName('eSIM, Unlimited, 30 Days, Europe, Turkey, V3'));
        $this->assertSame('Nigeria', EsimCatalogue::cleanPlanName('Nigeria'));
    }

    public function test_a_name_that_is_all_tokens_is_never_blanked(): void
    {
        $this->assertSame('eSIM, 1GB', EsimCatalogue::cleanPlanName('eSIM, 1GB'));
    }

    public function test_single_column_grids_cannot_be_widened_by_a_plan_row(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/nx-layout.css');
        $this->assertMatchesRegularExpression('/\.ns-cards-grid \{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/', $css);
        $this->assertStringContainsString('.ns-plan { min-width: 0;', $css);
    }
}
