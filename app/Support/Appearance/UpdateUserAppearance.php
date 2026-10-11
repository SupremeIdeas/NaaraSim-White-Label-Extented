<?php

namespace App\Support\Appearance;

use App\Models\User;
use App\Models\UserAppearance;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The ONE write path for a member's appearance (Prompt 20 §17.4 rule 7). Policy: own record only, values validated
 * against what is enabled right now, throttled, refused when the operator has locked appearance (admins exempt).
 * Returns the RESOLVED values so the UI shows what is really applied.
 */
class UpdateUserAppearance
{
    private const FIELDS = ['skin', 'accent', 'accent_hex', 'mode', 'round', 'dens', 'ts', 'depth', 'font', 'motion'];

    /**
     * @param  array<string, mixed>  $input  any of: skin, accent, accent_hex, mode, round, dens, ts, depth, font, motion
     *                                       (null = clear back to the platform default)
     * @return array{skin: string, accent: string, accent_hex: ?string, mode: ?string, dials: array<string, string>, locked: bool, user: bool}
     *
     * @throws AppearanceException
     */
    public function __invoke(User $user, array $input): array
    {
        $platform = AppearanceResolver::platform();
        if ($platform['locked'] && ! AppearanceResolver::isAdmin($user)) {
            throw new AppearanceException((string) __('appearance.err.locked'));
        }
        $key = 'appearance-save:'.$user->id;
        $max = (int) config('appearance.throttle_per_minute', 30);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new AppearanceException((string) __('appearance.err.slow_down'));
        }
        RateLimiter::hit($key, 60);

        $row = UserAppearance::firstOrNew(['user_id' => $user->id]);
        $input = array_intersect_key($input, array_flip(self::FIELDS));

        if (array_key_exists('skin', $input)) {
            $v = $input['skin'];
            if ($v !== null && ! in_array($v, $platform['skins'], true)) {
                throw new AppearanceException((string) __('appearance.err.invalid'));
            }
            $row->skin_key = $v;
        }

        if (array_key_exists('accent', $input)) {
            $v = $input['accent'];
            if ($v === 'custom') {
                $hex = strtolower((string) ($input['accent_hex'] ?? $row->accent_hex ?? ''));
                if (! $platform['allow_custom'] || ! AccentDeriver::isValidHex($hex)) {
                    throw new AppearanceException((string) __('appearance.err.invalid'));
                }
                $row->accent_key = 'custom';
                $row->accent_hex = $hex;
            } else {
                if ($v !== null && ! in_array($v, $platform['accents'], true)) {
                    throw new AppearanceException((string) __('appearance.err.invalid'));
                }
                $row->accent_key = $v;
                $row->accent_hex = null;   // choosing any preset (or null) is also the "reset" for a custom colour
            }
        } elseif (array_key_exists('accent_hex', $input)) {
            throw new AppearanceException((string) __('appearance.err.invalid'));   // a hex only ever travels with accent=custom
        }

        if (array_key_exists('mode', $input)) {
            if ($input['mode'] !== null && ! in_array($input['mode'], (array) config('appearance.modes'), true)) {
                throw new AppearanceException((string) __('appearance.err.invalid'));
            }
            $row->mode = $input['mode'];
        }

        foreach (UserAppearance::DIALS as $dial) {
            if (array_key_exists($dial, $input)) {
                $v = $input[$dial];
                if ($v !== null && ! array_key_exists($v, config("appearance.dials.{$dial}.options"))) {
                    throw new AppearanceException((string) __('appearance.err.invalid'));
                }
                $row->{$dial} = $v;
            }
        }

        $row->user_id = $user->id;
        $row->save();
        AppearanceResolver::forget($user->id);

        return AppearanceResolver::for($user);
    }
}
