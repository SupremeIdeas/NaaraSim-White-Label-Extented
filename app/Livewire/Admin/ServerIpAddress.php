<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\ServerEgressIp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > System > Server IP address. The one address a supplier (Paga, VTpass, a payout rail...) must have on its whitelist: where this server's outbound
 * requests leave from. It is hidden until the signed-in super admin re-enters their password (rate limited, audited), then shown for two minutes with a copy
 * button and masks itself again. Works the same on a fresh install, shared hosting and a VPS.
 */
#[Layout('components.layouts.admin')]
class ServerIpAddress extends Component
{
    public bool $asking = false;

    public string $password = '';

    public ?string $error = null;

    public ?string $ip = null;

    public ?string $site = null;

    public ?int $until = null;

    public bool $failed = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 404);
    }

    public function ask(): void
    {
        $this->asking = true;
        $this->error = null;
    }

    public function cancel(): void
    {
        $this->reset('asking', 'password', 'error');
    }

    public function reveal(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 404);
        $this->error = null;
        $user = Auth::user();
        if ((string) $user->getAuthPassword() === '') {
            $this->error = 'Set a password on your account first (Account, Security). It is how we know it is you.';

            return;
        }
        $bucket = 'server-ip-reveal:'.$user->id;
        if (RateLimiter::tooManyAttempts($bucket, 5)) {
            $this->error = 'Too many attempts. Try again in a few minutes.';

            return;
        }
        if (! Hash::check($this->password, (string) $user->getAuthPassword())) {
            RateLimiter::hit($bucket, 600);
            $this->password = '';
            $this->error = 'That password is not right.';

            return;
        }
        RateLimiter::clear($bucket);
        $this->password = '';
        $this->load(false);
        Auditor::log('server_ip.viewed', null, null, ['found' => $this->ip !== null]);
        $this->asking = false;
        $this->until = now()->addMinutes(2)->timestamp;
    }

    /** Ask again (only while the address is showing, so it is already behind the password). */
    public function recheck(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin') && $this->until !== null && now()->timestamp < $this->until, 404);
        $this->load(true);
        $this->until = now()->addMinutes(2)->timestamp;
    }

    /** Polled while showing: puts the mask back when the two minutes are up. */
    public function tick(): void
    {
        if ($this->until !== null && now()->timestamp >= $this->until) {
            $this->hide();
        }
    }

    public function hide(): void
    {
        $this->reset('ip', 'site', 'until', 'failed', 'asking', 'password', 'error');
    }

    private function load(bool $fresh): void
    {
        $this->ip = ServerEgressIp::get($fresh);
        $this->failed = $this->ip === null;
        $own = (string) request()->server('SERVER_ADDR', '');
        $this->site = filter_var($own, FILTER_VALIDATE_IP) ? $own : null;
    }

    public function render()
    {
        return view('livewire.admin.server-ip-address');
    }
}
