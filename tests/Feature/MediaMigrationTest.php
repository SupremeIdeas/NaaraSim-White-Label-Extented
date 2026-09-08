<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploads land on the local disk first; once Wasabi keys go live the scheduled
 * media:migrate-to-wasabi command promotes the important platform media to the
 * cloud and rewrites the stored URLs. It no-ops without keys and is idempotent.
 */
class MediaMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWasabi(): void
    {
        config([
            'filesystems.disks.wasabi.key' => 'test-key',
            'filesystems.disks.wasabi.secret' => 'test-secret',
            'filesystems.disks.wasabi.bucket' => 'test-bucket',
        ]);
        Storage::fake('public');
        Storage::fake('wasabi');
    }

    public function test_without_keys_the_command_does_nothing(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null, 'filesystems.disks.wasabi.secret' => null, 'filesystems.disks.wasabi.bucket' => null]);
        Setting::setValue('brand.favicon', '/storage/brand/icon.png');

        $this->artisan('media:migrate-to-wasabi')->assertSuccessful();

        // Untouched — still the local URL.
        $this->assertSame('/storage/brand/icon.png', Setting::getValue('brand.favicon'));
    }

    public function test_it_promotes_a_local_asset_and_rewrites_the_url(): void
    {
        $this->fakeWasabi();
        Storage::disk('public')->put('brand/icon.png', 'PNGDATA', 'public');
        Setting::setValue('brand.favicon', Storage::disk('public')->url('brand/icon.png'));

        $this->artisan('media:migrate-to-wasabi')->assertSuccessful();

        // File is now on Wasabi and the setting points at the cloud URL.
        Storage::disk('wasabi')->assertExists('brand/icon.png');
        $this->assertSame(Storage::disk('wasabi')->url('brand/icon.png'), Setting::getValue('brand.favicon'));
    }

    public function test_an_external_url_is_left_alone(): void
    {
        $this->fakeWasabi();
        Setting::setValue('brand.favicon', 'https://cdn.example.com/icon.png');

        $this->artisan('media:migrate-to-wasabi')->assertSuccessful();

        $this->assertSame('https://cdn.example.com/icon.png', Setting::getValue('brand.favicon'));
        $this->assertNull(MediaStorage::migrateLocalUrlToCloud('https://cdn.example.com/icon.png'));
    }
}
