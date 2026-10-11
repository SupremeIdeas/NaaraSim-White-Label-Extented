<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhoneGutterTest extends TestCase
{
    public function test_the_app_body_adds_no_second_gutter_inside_the_shell_on_phones(): void
    {
        $css = file_get_contents(base_path('resources/css/nx-layout.css'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 640px\) \{\s*\[data-nx-page\] \.ns-app__body \{ padding: 8px 0 20px; \}/', $css);
        $this->assertStringContainsString('data-nx-page', file_get_contents(resource_path('views/components/app-shell.blade.php')));
    }
}
