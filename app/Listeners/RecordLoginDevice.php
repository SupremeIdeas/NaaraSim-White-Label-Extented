<?php

namespace App\Listeners;

use App\Models\KnownDevice;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

/**
 * Records the IP+user-agent device fingerprint for every login and flags a
 * genuinely new one (Sept-14 owner request — 2FA/device-login alerts). A
 * user's very first-ever login never alerts (there is nothing to compare
 * against yet, and everyone's first device is "new" by definition).
 */
class RecordLoginDevice
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $fingerprint = KnownDevice::fingerprint($ip, $userAgent);

        $existing = KnownDevice::where('user_id', $user->id)->where('fingerprint', $fingerprint)->first();
        if ($existing !== null) {
            $existing->forceFill(['last_seen_at' => now()])->save();

            return;
        }

        $hasAnyKnownDevice = KnownDevice::where('user_id', $user->id)->exists();

        KnownDevice::create([
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        if ($hasAnyKnownDevice) {
            $user->notify(new NewDeviceLoginNotification($ip, $userAgent));
        }
    }
}
