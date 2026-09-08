<?php

namespace App\Services\Social;

use App\Models\BrandPartnerVideo;
use App\Models\BrandVideoWatchClaim;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\DailyCreditCap;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Server-confirmed video watch-time credit (BUILD-9 §3). The credit is NOT
 * granted on a single client "I watched it" message (trivially forgeable from
 * the console). Instead the client sends heartbeats carrying the player's real
 * currentTime + a short-lived signed token; the server accumulates confirmed
 * watch-time from FORWARD progress only (each step capped so a seek to the end
 * can't fake it), and only once ≥60s of confirmed watch-time accrues does it
 * grant — once per (user, video), respecting the daily cap. Meaningfully harder
 * to fake than a click, without needing to be cryptographically bulletproof.
 */
class VideoWatchService
{
    private const REQUIRED_SECONDS = 60;

    /** Max watch-time a single heartbeat can add (guards against seeking). */
    private const MAX_STEP = 15;

    private const REWARD_SETTING = 'credits.video_watch_reward';

    private const DEFAULT_REWARD = 5.0;

    public function __construct(private readonly CreditService $credits)
    {
    }

    public static function reward(): float
    {
        try {
            $v = (float) Setting::getValue(self::REWARD_SETTING, self::DEFAULT_REWARD);
        } catch (\Throwable) {
            return self::DEFAULT_REWARD;
        }

        return max(0.0, $v);
    }

    /** Begin a view session — returns a signed token the client echoes in heartbeats. */
    public function start(User $user, BrandPartnerVideo $video): array
    {
        $sid = (string) Str::uuid();
        Cache::put($this->key($sid), ['u' => $user->id, 'v' => $video->id, 'confirmed' => 0.0, 'last' => null], now()->addHours(2));

        return [
            'session' => $sid,
            'token' => Crypt::encryptString(json_encode(['u' => $user->id, 'v' => $video->id, 's' => $sid])),
        ];
    }

    /**
     * Process a heartbeat. Returns the confirmed seconds and, once the threshold
     * is crossed, the claim result.
     *
     * @return array{confirmed: int, required: int, claimed: bool, earned: float, capped: bool, already: bool}
     */
    public function heartbeat(User $user, string $token, float $currentTime): array
    {
        $data = $this->decode($token);
        if (! $data || (int) $data['u'] !== (int) $user->id) {
            return $this->status(0, false, 0.0, false, false);
        }

        $video = BrandPartnerVideo::find($data['v']);
        if (! $video) {
            return $this->status(0, false, 0.0, false, false);
        }

        $key = $this->key($data['s']);
        $state = Cache::get($key, ['u' => $user->id, 'v' => $video->id, 'confirmed' => 0.0, 'last' => null]);

        // Accumulate forward progress only, each step capped.
        if ($state['last'] !== null && $currentTime > $state['last']) {
            $state['confirmed'] += min($currentTime - $state['last'], self::MAX_STEP);
        }
        $state['last'] = max($currentTime, (float) ($state['last'] ?? 0));

        $confirmed = (int) floor($state['confirmed']);
        $alreadyClaimed = BrandVideoWatchClaim::where('user_id', $user->id)->where('video_id', $video->id)->exists();

        if ($alreadyClaimed) {
            Cache::put($key, $state, now()->addHours(2));

            return $this->status($confirmed, false, 0.0, false, true);
        }

        if ($confirmed < self::REQUIRED_SECONDS) {
            Cache::put($key, $state, now()->addHours(2));

            return $this->status($confirmed, false, 0.0, false, false);
        }

        // Threshold reached — grant once, respecting the daily cap.
        if (DailyCreditCap::isReached($user)) {
            Cache::put($key, $state, now()->addHours(2));

            return $this->status($confirmed, false, 0.0, true, false);
        }

        $earned = $this->grant($user, $video);
        Cache::put($key, $state, now()->addHours(2));

        return $this->status($confirmed, $earned['earned'] > 0, $earned['earned'], false, $earned['already']);
    }

    private function grant(User $user, BrandPartnerVideo $video): array
    {
        $reward = self::reward();

        return DB::transaction(function () use ($user, $video, $reward) {
            try {
                BrandVideoWatchClaim::create([
                    'user_id' => $user->id, 'video_id' => $video->id, 'watched_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return ['earned' => 0.0, 'already' => true];
            }
            if ($reward > 0) {
                $this->credits->earn($user, $reward, 'brand_video', "brand_video:{$user->id}:{$video->id}", 'Watched a brand video');
            }

            return ['earned' => $reward, 'already' => false];
        });
    }

    private function decode(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) && isset($data['u'], $data['v'], $data['s']) ? $data : null;
    }

    private function key(string $sid): string
    {
        return 'vwatch:'.$sid;
    }

    private function status(int $confirmed, bool $claimed, float $earned, bool $capped, bool $already): array
    {
        return [
            'confirmed' => $confirmed, 'required' => self::REQUIRED_SECONDS,
            'claimed' => $claimed, 'earned' => $earned, 'capped' => $capped, 'already' => $already,
        ];
    }
}
