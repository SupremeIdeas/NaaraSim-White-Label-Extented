<?php

namespace App\Services\SMS;

use Illuminate\Support\Facades\DB;

/**
 * STOP / START handling for Naara Lines (engineering blueprint 5.1 compliance). The keyword must be the whole message (case-insensitive,
 * trimmed, trailing punctuation ignored), exactly as carriers define it, so "please don't stop" is never treated as an opt-out.
 */
class SmsOptOuts
{
    public const STOP = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit'];

    public const START = ['start', 'unstop', 'yes'];

    public const HELP = ['help', 'info'];

    /** @return 'stop'|'start'|'help'|null */
    public static function keyword(?string $body): ?string
    {
        $w = strtolower(trim((string) $body, " \t\n\r\0\x0B.!"));
        if (in_array($w, self::STOP, true)) {
            return 'stop';
        }
        if (in_array($w, self::START, true)) {
            return 'start';
        }

        return in_array($w, self::HELP, true) ? 'help' : null;
    }

    public function apply(int $virtualNumberId, string $peer, ?string $body): ?string
    {
        $kw = self::keyword($body);
        if ($kw === 'stop') {
            DB::table('sms_opt_outs')->updateOrInsert(['virtual_number_id' => $virtualNumberId, 'peer' => $peer], ['opted_out_at' => now()]);
        } elseif ($kw === 'start') {
            DB::table('sms_opt_outs')->where(['virtual_number_id' => $virtualNumberId, 'peer' => $peer])->delete();
        }

        return $kw;
    }

    public function isOptedOut(int $virtualNumberId, string $peer): bool
    {
        return DB::table('sms_opt_outs')->where(['virtual_number_id' => $virtualNumberId, 'peer' => $peer])->exists();
    }
}
