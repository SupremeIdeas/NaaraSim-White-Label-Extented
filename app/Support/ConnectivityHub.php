<?php

namespace App\Support;

use App\Models\User;

/**
 * The "My Connectivity" data hub (blueprint Section 12) — the single place that
 * turns a user's raw eSIM + number orders into the active/archived, Model-grouped
 * shape both the Dashboard summary and the dedicated My Lines page render. One
 * source of truth so the two never drift. The user never sees a provider name or
 * a cost here — only their own Models (Naara Data / Verify / Rent / Line).
 */
class ConnectivityHub
{
    /** A finished/dead number belongs in the Archive, not the active list.
     *  (sms_orders.status enum: pending | waiting | completed | cancelled | timeout.) */
    private const NUMBER_ARCHIVE_STATES = ['cancelled', 'timeout'];

    /** Group display order: permanent first, then rentals, then OTP. */
    private const GROUP_ORDER = ['naara_line', 'naara_rent', 'naara_verify'];

    /**
     * The full connectivity picture for a user:
     * esimsActive, esimsArchived, numberGroups, numbersArchived, and the counts
     * a slim summary needs (esimActiveCount, numberActiveCount, archivedCount).
     */
    public static function for(User $user): array
    {
        $esims = $user->esimOrders()->latest()->limit(40)->get();
        $numbers = $user->smsOrders()->latest()->limit(60)->get();

        $esimArchived = fn ($e) => $e->status === 'expired'
            || ($e->expires_at !== null && $e->expires_at->isPast());
        $numberArchived = fn ($n) => in_array($n->status, self::NUMBER_ARCHIVE_STATES, true);

        $numberGroups = $numbers->reject($numberArchived)
            ->groupBy(fn ($n) => self::modelKeyFor($n))
            ->map(fn ($items, $key) => [
                'model' => ProviderModels::find($key) ?? ProviderModels::find('naara_verify'),
                'items' => $items->values(),
            ]);

        // Permanent numbers (Naara Line) live in VirtualNumber, not SmsOrder —
        // PermanentNumberRouter never writes an SmsOrder row for one. Without
        // this merge a purchased Naara Line was invisible on this exact page
        // (and the Dashboard summary that shares this hub) — confirmed by
        // reading PermanentNumberRouter before assuming otherwise.
        $lines = $user->virtualNumbers()->latest()->limit(20)->get();
        $lineArchived = fn ($l) => $l->status === 'expired';
        $linesActive = $lines->reject($lineArchived)->values();
        $linesArchived = $lines->filter($lineArchived)->values();

        if ($linesActive->isNotEmpty()) {
            $existing = $numberGroups->get('naara_line');
            $numberGroups->put('naara_line', [
                'model' => ProviderModels::find('naara_line'),
                'items' => $existing ? $existing['items']->concat($linesActive)->values() : $linesActive,
            ]);
        }

        $numberGroups = $numberGroups
            ->sortBy(fn ($g, $key) => array_search($key, self::GROUP_ORDER) === false
                ? 99 : array_search($key, self::GROUP_ORDER))
            ->values();

        $esimsActive = $esims->reject($esimArchived)->values();
        $esimsArchived = $esims->filter($esimArchived)->values();
        $numbersArchived = $numbers->filter($numberArchived)->values()->concat($linesArchived)->values();
        $numberActiveCount = $numberGroups->sum(fn ($g) => $g['items']->count());

        return [
            'esimsActive' => $esimsActive,
            'esimsArchived' => $esimsArchived,
            'numberGroups' => $numberGroups,
            'numbersArchived' => $numbersArchived,
            'hasAny' => $esims->isNotEmpty() || $numbers->isNotEmpty() || $lines->isNotEmpty(),
            'esimActiveCount' => $esimsActive->count(),
            'numberActiveCount' => $numberActiveCount,
            'archivedCount' => $esimsArchived->count() + $numbersArchived->count(),
        ];
    }

    /** Resolve a number's public Model key: prefer the recorded type, else lane. */
    private static function modelKeyFor($number): string
    {
        $model = ProviderModels::forNumberType($number->type)
            ?? ProviderModels::forProvider((string) $number->provider);

        return $model['key'] ?? 'naara_verify';
    }
}
