<?php

namespace App\PayoutRails\Grey;

use App\Models\PayoutAccount;

/**
 * Maps a saved PayoutAccount onto Grey's `beneficiary` object. Only fields we actually hold are sent;
 * Grey's /v1/payout-methods `required_fields` decide what is missing (see {@see missing()}).
 */
class GreyBeneficiaryMapper
{
    /** The extra details we can carry on a beneficiary (all optional; Grey reports what a corridor lacks). */
    public const DETAIL_FIELDS = ['routing_number', 'sort_code', 'swift_code', 'iban', 'account_type', 'branch_code', 'document_number', 'phone_number', 'recipient_type', 'wallet_id'];

    /**
     * @param  array<string, mixed>|null  $method  the matching /v1/payout-methods entry (picks a payment purpose Grey accepts)
     * @return array<string, string>
     */
    public function map(PayoutAccount $account, ?array $method = null): array
    {
        [$first, $last] = $this->names((string) $account->account_name);
        $details = (array) ($account->details ?? []);
        $country = strtoupper((string) $account->country);
        $currency = strtoupper((string) $account->currency);
        $mobile = $account->method === 'mobile_money' || $account->type === 'mobile_money';

        $b = [
            'first_name' => $first,
            'last_name' => $last,
            'account_number' => (string) $account->account_number,
            'bank_country_code' => $country,
        ];
        if (($purpose = $this->purpose($method)) !== null) {
            $b['payment_purpose'] = $purpose;
        }

        if ($mobile) {
            $b['destination'] = 'mobile_money';
            $b['network_type'] = (string) $account->bank_code;
            $b['source_of_funds'] = (string) config('services.grey.source_of_funds', 'BUSINESS_INCOME');
            foreach (['phone_number', 'wallet_id'] as $f) {
                if (filled($details[$f] ?? null)) {
                    $b[$f] = (string) $details[$f];
                }
            }
        } else {
            if (filled($account->bank_code)) {
                $b['bank_code'] = (string) $account->bank_code;
            }
            if (filled($account->bank_name)) {
                $b['bank_name'] = (string) $account->bank_name;
            }
            foreach (self::DETAIL_FIELDS as $f) {
                if (filled($details[$f] ?? null)) {
                    $b[$f] = (string) $details[$f];
                }
            }
            if ($currency === 'USD') {
                // Grey rejects USD bank payouts without a scheme: ACH for US accounts, SWIFT otherwise.
                $b['scheme'] = $country === 'US' ? 'ACH' : 'SWIFT';
            }
        }

        return $b;
    }

    /**
     * A payment purpose Grey accepts for this method. Grey publishes the exact list ("send one verbatim"), so the
     * configured value is used only when it is on the list; otherwise "Other", otherwise nothing is sent.
     *
     * @param  array<string, mixed>|null  $method
     */
    private function purpose(?array $method): ?string
    {
        $allowed = (array) ($method['payment_purposes'] ?? []);
        if ($allowed === []) {
            return $method === null ? (string) config('services.grey.payment_purpose', 'Other') : null;
        }
        foreach ([(string) config('services.grey.payment_purpose', 'Other'), 'Other'] as $want) {
            if (in_array($want, $allowed, true)) {
                return $want;
            }
        }

        return null;
    }

    /**
     * Required fields (per Grey's payout-methods) that this beneficiary doesn't carry.
     *
     * @param  array<string, string>  $beneficiary
     * @return list<string>
     */
    public function missing(array $beneficiary, array $method): array
    {
        $out = [];
        foreach ((array) ($method['required_fields'] ?? []) as $field) {
            $f = (string) preg_replace('/^beneficiary\./', '', (string) $field);
            if (! filled($beneficiary[$f] ?? null)) {
                $out[] = $f;
            }
        }

        return $out;
    }

    /**
     * The payout method that covers a currency + country (+ rail), or null.
     *
     * @param  list<array<string, mixed>>  $methods
     * @return array<string, mixed>|null
     */
    public function methodFor(array $methods, string $currency, string $country, bool $mobile): ?array
    {
        foreach ($methods as $m) {
            // Only the two rails we can collect details for. interac / pix / upi / wallet_address publish no field list.
            if (($m['name'] ?? '') !== ($mobile ? 'mobile_money' : 'bank_account')) {
                continue;
            }
            $cur = array_map('strtoupper', (array) ($m['currencies'] ?? []));
            $cty = array_map('strtoupper', (array) ($m['countries'] ?? []));
            if (in_array(strtoupper($currency), $cur, true) && ($cty === [] || in_array(strtoupper($country), $cty, true))) {
                return $m;
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string} */
    private function names(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 0) {
            return ['', ''];
        }
        if (count($parts) === 1) {
            return [$parts[0], $parts[0]];
        }
        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }
}
