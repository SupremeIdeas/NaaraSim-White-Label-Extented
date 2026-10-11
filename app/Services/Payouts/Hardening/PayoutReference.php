<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayoutRequest;
use Illuminate\Support\Str;

/**
 * The ONLY generator of provider-facing references (Addendum D-3.14). Internal references such as
 * `wd:{uuid}` contain characters (':') and a length providers may refuse, so the id we put on the wire
 * is derived: `ns{env}{base36 request id}-{random}` — lower-case letters, digits and '-' only, never
 * longer than the configured maximum, unique, and stored on the request so a retry reuses it.
 * A cancelled/failed request never has its reference reused: an admin retry is a new request.
 */
class PayoutReference
{
    public static function make(PayoutRequest $request, ?string $provider = null): string
    {
        $env = match (app()->environment()) {
            'production' => 'p', 'staging' => 's', default => 'd',
        };
        $max = (int) (config('payouts.reference.providers.'.$provider.'.max_length') ?? config('payouts.reference.max_length', 32));
        $head = 'ns'.$env.base_convert((string) $request->id, 10, 36).'-';
        $tail = strtolower(Str::random(max(8, $max - strlen($head))));

        return substr($head.$tail, 0, $max);
    }

    /** Stamp it once. Safe to call repeatedly. */
    public static function ensure(PayoutRequest $request): string
    {
        if (blank($request->provider_reference)) {
            $request->forceFill(['provider_reference' => self::make($request, $request->provider)])->save();
        }

        return $request->provider_reference;
    }
}
