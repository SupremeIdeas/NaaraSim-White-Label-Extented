<?php

namespace App\Services\Topup;

use App\Models\TopupBeneficiary;
use App\Models\TopupNumberOperator;
use App\Support\TopupLogos;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Throwable;

/**
 * Turns whatever a person types into: a valid E.164 number, its country, and the NETWORK it is on, with the evidence.
 *
 * Layers, strongest first (numbers are portable, so one source is never trusted blindly):
 *   1. supplier   a live lookup by a supplier that has one (Ding GetAccountLookup, Reloadly auto-detect)
 *   2. confirmed  a past DELIVERED top-up to this exact number told us its network (catches ported numbers)
 *   3. saved      the network the customer saved on a beneficiary
 *   4. prefix     the carrier allocation table (Google libphonenumber data, committed), longest prefix wins
 * The result says which layer answered, so the screen can ask "is that right?" when the evidence is only a prefix.
 */
class NumberResolver
{
    /** Country-specific names for the same network (a carrier table and a supplier catalogue rarely spell it alike). */
    private const ALIASES = [
        'NG' => ['etisalat' => 'ninemobile', '9mobile' => 'ninemobile', 'mtel' => 'ntel', 'nitel' => 'ntel'],
        'GH' => ['vodafone' => 'telecel', 'airtel' => 'airteltigo', 'tigo' => 'airteltigo'],
        'KE' => ['telkom' => 'kenyatelkom', 'telkomkenya' => 'kenyatelkom'],
        'ZA' => ['telkom' => 'telkommobile', 'cellc' => 'cellc'],
    ];

    /** @var array<string, array<string, string>>|null */
    private static ?array $prefixes = null;

    public function __construct(private ProviderRouter $router, private TopupCatalogue $catalogue) {}

    /** @return array<string, array<string, string>> calling code => prefix => carrier */
    private static function prefixes(): array
    {
        if (self::$prefixes === null) {
            $f = base_path('database/seeders/topup-prefixes.json');
            self::$prefixes = is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
        }

        return self::$prefixes;
    }

    /**
     * @param  string|null  $countryHint  ISO used to read a number typed without its + prefix
     * @return array{valid: bool, error: ?string, e164: string, country: ?string, national: ?string, operator: ?string, carrier: ?string, source: string, sure: bool, operators: list<string>}
     */
    public function resolve(string $raw, ?string $countryHint = null, ?int $userId = null): array
    {
        $out = ['valid' => false, 'error' => null, 'e164' => '', 'country' => null, 'national' => null, 'operator' => null, 'carrier' => null, 'source' => 'none', 'sure' => false, 'operators' => []];
        $raw = trim($raw);
        if ($raw === '' || strlen(preg_replace('/\D/', '', $raw) ?? '') < 5) {
            return $out;
        }
        $u = PhoneNumberUtil::getInstance();
        try {
            $hint = $countryHint ? strtoupper($countryHint) : null;
            $n = $u->parse($raw, str_starts_with($raw, '+') || str_starts_with($raw, '00') ? null : $hint);
        } catch (NumberParseException) {
            $out['error'] = 'Enter the number with its area or network code.';

            return $out;
        }
        $region = $u->getRegionCodeForNumber($n) ?: ($countryHint ? strtoupper($countryHint) : null);
        if (! $u->isValidNumber($n)) {
            $out['country'] = $region;
            $out['error'] = 'That number does not look right'.($region ? ' for '.\App\Support\CountryNames::name($region) : '').'. Check it and try again.';

            return $out;
        }
        $out['valid'] = true;
        $out['e164'] = $u->format($n, PhoneNumberFormat::E164);
        $out['country'] = $region;
        $out['national'] = $u->format($n, PhoneNumberFormat::NATIONAL);
        $out['operators'] = $region ? $this->catalogue->operators($region)->all() : [];

        $this->identify($out, $userId);

        return $out;
    }

    /** @param  array<string, mixed>  $out */
    private function identify(array &$out, ?int $userId): void
    {
        $country = (string) $out['country'];
        $ops = (array) $out['operators'];

        // 1. live supplier lookup (only suppliers that have one answer; it is cached for ten minutes per number)
        $live = \Illuminate\Support\Facades\Cache::remember('topup.lookup.'.sha1($out['e164']), 600, function () use ($out, $country) {
            foreach ($this->router->chain($country, 'airtime') as $code) {
                try {
                    $r = TopupProviders::get($code)->lookupRecipient($out['e164'], $country);
                } catch (Throwable) {
                    continue;
                }
                if ($r?->operatorName) {
                    return $r->operatorName;
                }
            }

            return '';
        });
        if ($live && ($m = $this->match($live, $ops, $country))) {
            $out = array_merge($out, ['operator' => $m, 'carrier' => $live, 'source' => 'supplier', 'sure' => true]);

            return;
        }

        // 2. a delivered top-up to this exact number confirmed its network
        $conf = TopupNumberOperator::where('phone_e164', $out['e164'])->first();
        if ($conf && ($m = $this->match($conf->operator_name, $ops, $country))) {
            $out = array_merge($out, ['operator' => $m, 'carrier' => $conf->operator_name, 'source' => 'confirmed', 'sure' => true]);

            return;
        }

        // 3. the customer saved it
        if ($userId) {
            $b = TopupBeneficiary::where('user_id', $userId)->where('phone_e164', $out['e164'])->first();
            if ($b?->operator_name && ($m = $this->match($b->operator_name, $ops, $country))) {
                $out = array_merge($out, ['operator' => $m, 'carrier' => $b->operator_name, 'source' => 'saved', 'sure' => true]);

                return;
            }
        }

        // 4. carrier allocation prefix
        $carrier = $this->carrierByPrefix(ltrim($out['e164'], '+'));
        if ($carrier) {
            $out['carrier'] = $carrier;
            if ($m = $this->match($carrier, $ops, $country)) {
                $out = array_merge($out, ['operator' => $m, 'source' => 'prefix', 'sure' => false]);
            }
        }
    }

    /** Longest matching prefix of the full international number (calling code included). */
    public function carrierByPrefix(string $digits): ?string
    {
        foreach (self::prefixes() as $cc => $table) {
            if (! str_starts_with($digits, (string) $cc)) {
                continue;
            }
            for ($l = min(strlen($digits), 12); $l >= strlen((string) $cc) + 1; $l--) {
                if (isset($table[substr($digits, 0, $l)])) {
                    return $table[substr($digits, 0, $l)];
                }
            }
        }

        return null;
    }

    /** The catalogue operator that a carrier name refers to, or null. @param  list<string>  $operators */
    public function match(string $carrier, array $operators, string $country): ?string
    {
        $k = TopupLogos::key($carrier);
        $k = self::ALIASES[strtoupper($country)][$k] ?? $k;
        $byKey = [];
        foreach ($operators as $op) {
            $byKey[TopupLogos::key($op)] = $op;
        }
        if (isset($byKey[$k])) {
            return $byKey[$k];
        }
        foreach ($byKey as $ok => $op) {
            if (strlen($k) >= 3 && strlen($ok) >= 3 && (str_contains($ok, $k) || str_contains($k, $ok))) {
                return $op;
            }
        }

        return null;
    }

    /** Remember what a DELIVERED top-up proved about a number. */
    public function learn(string $e164, string $country, string $operator): void
    {
        $r = TopupNumberOperator::firstOrNew(['phone_e164' => $e164]);
        $same = $r->exists && strcasecmp((string) $r->operator_name, $operator) === 0;
        $r->fill(['country_iso' => $country, 'operator_name' => $operator, 'confirmations' => $same ? $r->confirmations + 1 : 1, 'last_confirmed_at' => now()])->save();
    }
}
