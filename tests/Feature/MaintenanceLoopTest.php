<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\MaintenanceProposal;
use App\Models\User;
use App\Services\Maintenance\ClaudeFixProposer;
use App\Services\Maintenance\Contracts\CodeHostClient;
use App\Services\Maintenance\Contracts\FixProposer;
use App\Services\Maintenance\GitHubCodeHostClient;
use App\Services\Maintenance\MaintenanceLoop;
use App\Services\Maintenance\ProposedFix;
use App\Support\Maintenance\SecretGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MaintenanceLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function error(): ErrorLog
    {
        return ErrorLog::create([
            'code' => 'E-42', 'message' => 'Undefined array key "iccid"',
            'severity' => 'error', 'context' => ['file' => 'app/Services/Foo.php'],
        ]);
    }

    private function fakeProposer(ProposedFix $fix): FixProposer
    {
        return new class($fix) implements FixProposer
        {
            public function __construct(private ProposedFix $fix)
            {
            }

            public function available(): bool
            {
                return true;
            }

            public function propose(ErrorLog $error): ProposedFix
            {
                return $this->fix;
            }
        };
    }

    private function fakeHost(): CodeHostClient
    {
        return new class implements CodeHostClient
        {
            public bool $rolledBack = false;

            public function available(): bool
            {
                return true;
            }

            public function openPullRequest(ProposedFix $fix): array
            {
                return ['branch' => 'claude/maint-x', 'pr_url' => 'https://github.com/o/r/pull/7'];
            }

            public function rollback(string $branch, string $prUrl): void
            {
                $this->rolledBack = true;
            }
        };
    }

    private function safeFix(): ProposedFix
    {
        return new ProposedFix(
            title: 'Guard against a missing iccid',
            summary: 'Use null coalescing on the array key.',
            changes: ['app/Services/Foo.php' => "<?php\n// fixed\n"],
            diff: "--- a/app/Services/Foo.php\n+++ b/app/Services/Foo.php\n@@ -1 +1 @@\n-old\n+new\n",
        );
    }

    private function loop(FixProposer $proposer, CodeHostClient $host): MaintenanceLoop
    {
        return new MaintenanceLoop($proposer, $host, new SecretGuard);
    }

    public function test_analyze_creates_a_pending_proposal_from_a_real_error(): void
    {
        $error = $this->error();
        $proposal = $this->loop($this->fakeProposer($this->safeFix()), $this->fakeHost())->analyze($error);

        $this->assertSame(MaintenanceProposal::STATUS_PENDING, $proposal->status);
        $this->assertSame($error->id, $proposal->error_log_id);
        $this->assertArrayHasKey('app/Services/Foo.php', $proposal->changes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.proposed']);
    }

    public function test_a_fix_touching_a_secret_file_is_blocked(): void
    {
        $fix = new ProposedFix('Sneaky', 'nope', ['.env' => "APP_KEY=base64:zzz\n"], 'diff');

        try {
            $this->loop($this->fakeProposer($fix), $this->fakeHost())->analyze($this->error());
            $this->fail('Expected a secret-touching fix to be blocked.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(0, MaintenanceProposal::count());
    }

    public function test_a_fix_that_writes_a_secret_value_is_blocked(): void
    {
        $fix = new ProposedFix('Sneaky', 'nope',
            ['config/x.php' => "<?php return ['secret_key' => 'sk_live_ABCDEFGHIJKLMNOP'];"],
            'diff');

        $guard = new SecretGuard;
        $this->assertFalse($guard->isSafe($fix));
    }

    public function test_approval_opens_a_ci_gated_pr_and_is_super_admin_only(): void
    {
        $host = $this->fakeHost();
        $loop = $this->loop($this->fakeProposer($this->safeFix()), $host);
        $proposal = $loop->analyze($this->error());

        // A non-super-admin cannot approve.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        try {
            $loop->approve($proposal, $admin);
            $this->fail('Expected a non-super-admin approval to be refused.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // A super admin opens the PR.
        $loop->approve($proposal->fresh(), $this->superAdmin());
        $proposal->refresh();
        $this->assertSame(MaintenanceProposal::STATUS_PR_OPENED, $proposal->status);
        $this->assertSame('https://github.com/o/r/pull/7', $proposal->pr_url);
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.approved']);
    }

    public function test_reject_and_rollback_transition_and_audit(): void
    {
        $host = $this->fakeHost();
        $loop = $this->loop($this->fakeProposer($this->safeFix()), $host);
        $super = $this->superAdmin();

        $rejected = $loop->analyze($this->error());
        $loop->reject($rejected, $super);
        $this->assertSame(MaintenanceProposal::STATUS_REJECTED, $rejected->fresh()->status);

        $approved = $loop->analyze($this->error());
        $loop->approve($approved, $super);
        $loop->rollback($approved->fresh(), $super);

        $this->assertTrue($host->rolledBack);
        $this->assertSame(MaintenanceProposal::STATUS_ROLLED_BACK, $approved->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.rolled_back']);
    }

    public function test_analyze_is_refused_when_the_proposer_is_not_configured(): void
    {
        config(['services.anthropic.api_key' => null]);

        // The default container binding resolves the real (unconfigured) clients.
        try {
            app(MaintenanceLoop::class)->analyze($this->error());
            $this->fail('Expected analyze to be refused without configuration.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_clients_report_unavailable_without_configuration(): void
    {
        config([
            'services.anthropic.api_key' => null,
            'services.github_maintenance.token' => null,
            'services.github_maintenance.repo' => null,
        ]);

        $this->assertFalse((new ClaudeFixProposer)->available());
        $this->assertFalse((new GitHubCodeHostClient)->available());
    }

    public function test_the_maintenance_page_is_super_admin_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin)->get('/adminmaster/maintenance')->assertForbidden();
        $this->actingAs($this->superAdmin())->get('/adminmaster/maintenance')->assertOk();
    }
}
