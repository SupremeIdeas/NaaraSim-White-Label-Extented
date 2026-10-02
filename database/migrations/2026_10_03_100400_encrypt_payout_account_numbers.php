<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global Payout Layer — Phase 1.5: payout destinations (bank numbers, PayPal
 * emails, crypto addresses, Stripe account ids) are encrypted at rest.
 *
 *  - `account_number` becomes TEXT (ciphertext is far longer than the number).
 *  - `lookup_hash` (HMAC blind index, see PayoutAccount::lookupHashFor) is
 *    backfilled so duplicate/fingerprint checks never need the plaintext.
 *  - existing rows are re-encrypted in chunks, resumable: a row that already
 *    decrypts is left alone, so re-running (or a crashed run) is safe.
 *
 * The Stripe connected-account id is already mirrored in `provider_recipient_ref`
 * (StripeConnectService::findByAccountId now looks it up there), so nothing
 * depends on querying the encrypted column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->text('account_number')->change();
        });

        // Stripe rows created before provider_recipient_ref was populated.
        DB::table('payout_accounts')->where('provider', 'stripe')->whereNull('provider_recipient_ref')
            ->orderBy('id')->each(function ($row) {
                $plain = $this->plain($row->account_number);
                if ($plain !== null && str_starts_with($plain, 'acct_')) {
                    DB::table('payout_accounts')->where('id', $row->id)->update(['provider_recipient_ref' => $plain]);
                }
            });

        DB::table('payout_accounts')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $plain = $this->plain($row->account_number);
                if ($plain === null || $plain === '') {
                    continue;
                }
                DB::table('payout_accounts')->where('id', $row->id)->update([
                    'account_number' => $this->isEncrypted($row->account_number) ? $row->account_number : Crypt::encryptString($plain),
                    'lookup_hash' => $row->lookup_hash ?: $this->hash($plain),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('payout_accounts')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $plain = $this->plain($row->account_number);
                if ($plain !== null) {
                    DB::table('payout_accounts')->where('id', $row->id)->update(['account_number' => $plain]);
                }
            }
        });
        // The column stays TEXT: shrinking it back could truncate a long IBAN/address.
    }

    private function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /** The plaintext, whether the stored value is ciphertext or still plain. */
    private function plain(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    private function hash(string $plain): string
    {
        return \App\Models\PayoutAccount::lookupHashFor($plain);
    }
};
