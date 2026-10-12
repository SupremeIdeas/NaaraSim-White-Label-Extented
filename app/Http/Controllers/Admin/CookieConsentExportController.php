<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CookieConsentLog;
use App\Support\Auditor;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV of the cookie-consent log (proof of consent) for an auditor or a regulator's request. Admins only (the admin route group already enforces it); the address is never in it, only its hash. */
class CookieConsentExportController extends Controller
{
    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
        Auditor::log('cookies.log_exported');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['when_utc', 'consent_id', 'user_id', 'policy_version', 'action', 'functional', 'analytics', 'marketing', 'ip_hash', 'path']);
            CookieConsentLog::query()->orderBy('id')->chunkById(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->created_at?->utc()->toIso8601String(), $r->consent_id, $r->user_id, $r->policy_version, $r->action, (int) $r->functional, (int) $r->analytics, (int) $r->marketing, $r->ip_hash, $r->path]);
                }
            });
            fclose($out);
        }, 'cookie-consent-log-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}
