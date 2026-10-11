<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Engineering v3 Phase 1: the shared upload progress, layer manager and z-index scale stay wired in. */
class NxLayersAssetsTest extends TestCase
{
    public function test_the_upload_progress_and_layer_manager_are_registered_in_the_app_bundle(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("import { registerNxUpload } from './nx-upload';", $js);
        $this->assertStringContainsString('registerNxUpload();', $js);
        $this->assertStringContainsString("import { registerNxLayers } from './nx-layers';", $js);
        $this->assertStringContainsString('registerNxLayers();', $js);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import './nx-upload.css';", $css);
        $this->assertStringContainsString("@import './nx-layers.css';", $css);
    }

    public function test_the_upload_card_is_accessible_and_honours_reduced_motion(): void
    {
        $js = file_get_contents(resource_path('js/nx-upload.js'));
        $this->assertStringContainsString('role="progressbar"', $js);
        $this->assertStringContainsString('aria-valuenow', $js);
        $this->assertStringContainsString("aria-live", $js);
        foreach (['livewire-upload-start', 'livewire-upload-progress', 'livewire-upload-finish', 'livewire-upload-error', 'livewire-upload-cancel'] as $event) {
            $this->assertStringContainsString($event, $js, "{$event} must drive the card");
        }
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(resource_path('css/nx-upload.css')));
    }

    public function test_no_modal_sits_above_the_toast_layer_and_the_scale_is_documented(): void
    {
        $this->assertStringContainsString('z-[80]', file_get_contents(resource_path('views/components/ui/modal.blade.php')));
        $this->assertStringNotContainsString('z-[9998]', file_get_contents(resource_path('views/components/ui/modal.blade.php')));
        $layers = file_get_contents(resource_path('css/nx-layers.css'));
        foreach (['--nx-z-modal: 80', '--nx-z-toast: 100', '--nx-z-call: 120', '--nx-dock-h', 'overflow-x: clip'] as $needle) {
            $this->assertStringContainsString($needle, $layers);
        }
    }

    public function test_the_app_shell_reserves_the_measured_dock_height(): void
    {
        $shell = file_get_contents(resource_path('views/components/app-shell.blade.php'));
        $this->assertStringContainsString('var(--nx-dock-h', $shell);
        $this->assertStringNotContainsString('py-6 pb-28', $shell);
    }
}
