<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests render views that use @vite(); they must not depend on a
        // compiled asset manifest (public/build is git-ignored and not built
        // in the PHP CI job). withoutVite() stubs the directives.
        $this->withoutVite();

        // Treat the app as installed by default so the RedirectIfNotInstalled
        // middleware passes through. InstallerTest opts out to drive the wizard.
        \App\Support\Installer::markInstalled();
    }
}
