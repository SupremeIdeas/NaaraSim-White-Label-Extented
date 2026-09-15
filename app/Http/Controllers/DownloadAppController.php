<?php

namespace App\Http\Controllers;

use App\Support\AppExport;

/**
 * Public "Download the App" page (App Export §1). Android shows a direct APK
 * download the moment a signed APK build is marked ready — no store approval
 * needed for sideloading. iOS shows ONLY the App Store badge, and only once the
 * admin marks that listing live: outside the EU there is simply no web
 * direct-install path for iOS, so we never render a fake one.
 */
class DownloadAppController extends Controller
{
    public function __invoke()
    {
        abort_unless(AppExport::enabled(), 404);
        abort_unless(AppExport::get('download_enabled'), 404);

        $pageUrl = route('download');

        return view('marketing.download', [
            'appName' => AppExport::get('app_name'),
            'version' => AppExport::get('version'),
            'androidDownloadable' => AppExport::androidDownloadable(),
            'apkUrl' => AppExport::get('android_apk_url'),
            'androidBadge' => AppExport::androidBadgeVisible(),
            'androidStoreUrl' => AppExport::get('android_store_url'),
            'iosBadge' => AppExport::iosBadgeVisible(),
            'iosStoreUrl' => AppExport::get('ios_store_url'),
            'qr' => AppExport::qrDataUri($pageUrl, 200),
        ]);
    }
}
