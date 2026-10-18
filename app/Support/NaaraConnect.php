<?php

namespace App\Support;

use App\Models\EsimPlan;
use Illuminate\Support\Facades\Cache;

/**
 * Naara Connect (the Data + Calls eSIM line). It is OFF until something can actually deliver it, and the admin keeps the final say:
 * the `naara_connect` feature switch auto-turns on the moment a Full-eSIM provider (1GLOBAL, Gigs, Monty Mobile RSP) has its keys saved or a
 * synced plan carries voice minutes (Zendit sends those fields only on voice-enabled accounts), and the admin can switch it off or force it on.
 */
final class NaaraConnect
{
    /** Providers whose whole catalogue is voice + data (an MVNO stack). Zendit is judged by its plans, not by its key. */
    public const FULL_PROVIDERS = ['oneglobal', 'gigs', 'montymobile'];

    /** Something can deliver a Data + Calls eSIM right now: a Full-eSIM provider is keyed, or a voice plan has been synced. */
    public static function available(): bool
    {
        foreach (self::FULL_PROVIDERS as $p) {
            if (ProviderStatus::isActive($p)) {
                return true;
            }
        }

        return self::voicePlansExist();
    }

    public static function voicePlansExist(): bool
    {
        try {
            return Cache::remember('naara_connect.voice_plans', 60, fn () => EsimPlan::query()->where('is_active', true)->where('has_voice', true)->exists());
        } catch (\Throwable) {
            return false;
        }
    }

    /** Switched on by the admin (or auto-on) AND deliverable: the tab shows and the line sells. */
    public static function live(): bool
    {
        return FeatureFlags::enabled('naara_connect');
    }

    public static function flush(): void
    {
        Cache::forget('naara_connect.voice_plans');
    }
}
