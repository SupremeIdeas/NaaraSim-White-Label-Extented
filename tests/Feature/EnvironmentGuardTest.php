<?php

namespace Tests\Feature;

use App\Support\EnvironmentGuard;
use Tests\TestCase;

class EnvironmentGuardTest extends TestCase
{
    public function test_no_warnings_outside_production_even_when_misconfigured(): void
    {
        app()['env'] = 'local';
        config(['queue.default' => 'sync', 'app.debug' => true]);

        $this->assertSame([], EnvironmentGuard::warnings());
        $this->assertFalse(EnvironmentGuard::hasWarnings());
    }

    public function test_flags_sync_queue_and_debug_on_in_production(): void
    {
        app()['env'] = 'production';
        config(['queue.default' => 'sync', 'app.debug' => true]);

        $keys = array_column(EnvironmentGuard::warnings(), 'key');
        $this->assertContains('queue_sync', $keys);
        $this->assertContains('app_debug', $keys);
        $this->assertTrue(EnvironmentGuard::hasWarnings());
    }

    public function test_clean_production_config_has_no_warnings(): void
    {
        app()['env'] = 'production';
        config(['queue.default' => 'database', 'app.debug' => false]);

        $this->assertSame([], EnvironmentGuard::warnings());
    }
}
