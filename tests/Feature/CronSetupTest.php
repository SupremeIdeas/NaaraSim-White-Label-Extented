<?php

namespace Tests\Feature;

use App\Support\HostingGuide;
use App\Support\Installer;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The one-time cron/scheduler setup shown to operators (blueprint Section 20).
 */
class CronSetupTest extends TestCase
{
    public function test_the_cron_line_is_a_valid_every_minute_schedule_run(): void
    {
        $line = Installer::cronLine('php', '/var/www/naarasim');

        $this->assertSame('* * * * * cd /var/www/naarasim && php artisan schedule:run >> /dev/null 2>&1', $line);
        $this->assertStringContainsString('schedule:run', Installer::cronCommandOnly('php', '/var/www/naarasim'));
    }

    public function test_the_php_binary_never_returns_an_fpm_path(): void
    {
        // Web SAPI can report a php-fpm binary; the cron helper must fall back to
        // a plain "php" the cron daemon can resolve.
        $bin = Installer::phpBinary();
        $this->assertStringNotContainsString('fpm', $bin);
    }

    public function test_the_cron_setup_component_renders_the_command_with_a_copy_button(): void
    {
        $html = Blade::render('<x-cron-setup hosting="shared" />');

        $this->assertStringContainsString('schedule:run', $html);
        $this->assertStringContainsString('cPanel', $html);
        $this->assertStringContainsString('Copy', $html);
        // No emoji — SVG icons only (platform UI rule).
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    }

    public function test_the_vps_variant_shows_the_horizon_worker_command(): void
    {
        $html = Blade::render('<x-cron-setup hosting="vps" />');
        $this->assertStringContainsString('horizon', $html);
        $this->assertStringContainsString('supervisor', $html);
    }

    public function test_hosting_guide_delegates_to_the_installer_authority(): void
    {
        // Installer is the single source of truth for the binary/path/cron
        // strings; HostingGuide (System Health) must return byte-identical values,
        // so the fpm-safe fallback can never live in only one of them again.
        $this->assertSame(Installer::phpBinary(), HostingGuide::phpBinary());
        $this->assertSame(Installer::appPath(), HostingGuide::appPath());
        $this->assertSame(Installer::cronLine(), HostingGuide::cronLine());
        $this->assertSame(Installer::queueWorkerCommand(), HostingGuide::queueWorkerCommand());
    }

    public function test_the_vps_steps_carry_a_real_supervisor_block_and_deploy_reload(): void
    {
        $vps = HostingGuide::steps()['vps'];
        $flat = json_encode($vps);

        // A concrete, copy-paste Supervisor program — not just "add a process".
        $this->assertStringContainsString('[program:naarasim-horizon]', $flat);
        $this->assertStringContainsString('autorestart=true', $flat);
        // Redeploy reload is a visible step, not a buried code comment.
        $this->assertStringContainsString('horizon:terminate', $flat);
    }
}
