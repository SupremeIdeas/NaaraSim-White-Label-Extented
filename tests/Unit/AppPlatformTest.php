<?php

namespace Tests\Unit;

use App\Support\AppPlatform;
use PHPUnit\Framework\TestCase;

/**
 * AppPlatform (App Store payments-compliance doc, BUILD-5 §6) — the
 * `is_ios_build` capability flag the Wallet/Wizard/merchant-upgrade/Gift
 * screens read to restructure themselves on iOS specifically.
 */
class AppPlatformTest extends TestCase
{
    public function test_ios_native_ua_detected(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) median/1.0';
        $this->assertTrue(AppPlatform::isNativeApp($ua));
        $this->assertTrue(AppPlatform::isIosBuild($ua));
        $this->assertFalse(AppPlatform::isAndroidBuild($ua));
    }

    public function test_android_native_ua_detected(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36 median';
        $this->assertTrue(AppPlatform::isNativeApp($ua));
        $this->assertTrue(AppPlatform::isAndroidBuild($ua));
        $this->assertFalse(AppPlatform::isIosBuild($ua));
    }

    public function test_regular_mobile_safari_is_not_native(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $this->assertFalse(AppPlatform::isNativeApp($ua));
        $this->assertFalse(AppPlatform::isIosBuild($ua));
        $this->assertFalse(AppPlatform::isAndroidBuild($ua));
    }

    public function test_blank_ua_is_safe(): void
    {
        $this->assertFalse(AppPlatform::isNativeApp(''));
        $this->assertFalse(AppPlatform::isIosBuild(''));
        $this->assertFalse(AppPlatform::isAndroidBuild(''));
    }

    public function test_ipad_and_ipod_are_ios(): void
    {
        $this->assertTrue(AppPlatform::isIosBuild('... iPad ... median'));
        $this->assertTrue(AppPlatform::isIosBuild('... iPod ... median'));
    }
}
