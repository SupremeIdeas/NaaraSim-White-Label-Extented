<?php

namespace App\Support\Appearance;

use Illuminate\Support\Facades\Auth;

/** Small view helpers for skin wrappers. */
class PageSkin
{
    /** Passport's watermark code for the signed-in member, or null when the current skin is not Passport. */
    public static function countryCode(): ?string
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }
        $r = AppearanceResolver::for($user);

        return $r['country'] ? strtoupper($r['country']) : null;
    }
}
