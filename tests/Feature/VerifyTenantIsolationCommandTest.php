<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The tenant-isolation self-check command (NAARASIM-URGENT-TENANT-ISOLATION §4).
 */
class VerifyTenantIsolationCommandTest extends TestCase
{
    public function test_it_passes_on_a_well_isolated_config(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('session.domain', null);
        config()->set('cache.default', 'array');

        $this->artisan('tenant:verify-isolation')->assertExitCode(0);
    }

    public function test_it_hard_fails_on_an_empty_app_key(): void
    {
        config()->set('app.key', '');

        $this->artisan('tenant:verify-isolation')->assertExitCode(1);
    }

    public function test_a_leading_dot_session_domain_warns_and_is_strict_failable(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('session.domain', '.example.com');
        config()->set('cache.default', 'array');

        // Non-strict: a warning is not a failure.
        $this->artisan('tenant:verify-isolation')->assertExitCode(0);
        // Strict: the same warning fails the gate.
        $this->artisan('tenant:verify-isolation --strict')->assertExitCode(1);
    }

    public function test_redis_cache_without_a_prefix_warns(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('cache.default', 'redis');
        config()->set('cache.prefix', '');

        $this->artisan('tenant:verify-isolation --strict')->assertExitCode(1);
    }
}
