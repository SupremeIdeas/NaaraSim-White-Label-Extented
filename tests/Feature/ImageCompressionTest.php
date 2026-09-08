<?php

namespace Tests\Feature;

use App\Jobs\CompressImageJob;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Server-side WebP compression pipeline (BUILD-11 §3).
 */
class ImageCompressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD with WebP support is required for these tests.');
        }
    }

    /** A big, photographic-style PNG — huge as lossless PNG, so the lossy WebP
     *  pass genuinely wins on bytes like a real phone photo. Built by generating
     *  a small per-pixel noise tile (cheap) and resampling it up, which yields
     *  smooth, detailed, hard-to-PNG content without a multi-million-call loop. */
    private function largePngBytes(int $w = 1600, int $h = 1600): string
    {
        $tile = 160;
        $small = imagecreatetruecolor($tile, $tile);
        for ($y = 0; $y < $tile; $y++) {
            for ($x = 0; $x < $tile; $x++) {
                imagesetpixel($small, $x, $y, imagecolorallocate($small, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }
        $img = imagescale($small, $w, $h, IMG_BICUBIC);
        imagedestroy($small);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public function test_it_compresses_a_large_image_to_webp_in_place_at_the_same_path(): void
    {
        Storage::fake('public');
        $path = 'banners/big.png';
        Storage::disk('public')->put($path, $this->largePngBytes());
        $originalSize = strlen((string) Storage::disk('public')->get($path));

        (new CompressImageJob('public', $path, 'banners'))->handle();

        // Same path (URL stays valid), now smaller and actual WebP bytes.
        $this->assertTrue(Storage::disk('public')->exists($path));
        $out = (string) Storage::disk('public')->get($path);
        $this->assertLessThan($originalSize, strlen($out));
        $this->assertStringStartsWith('RIFF', $out);
        $this->assertSame('WEBP', substr($out, 8, 4));
    }

    public function test_it_downscales_to_the_context_max_dimension(): void
    {
        Storage::fake('public');
        // Avatars cap at 512px on the longest edge.
        $path = 'avatars/huge.png';
        Storage::disk('public')->put($path, $this->largePngBytes(1600, 1200));

        (new CompressImageJob('public', $path, 'avatars'))->handle();

        $info = getimagesizefromstring((string) Storage::disk('public')->get($path));
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(512, max($info[0], $info[1]));
    }

    public function test_a_failed_compression_leaves_the_original_intact(): void
    {
        Storage::fake('public');
        // Not a decodable image — the job must not blow up or truncate it.
        $path = 'banners/not-an-image.png';
        $garbage = 'this is definitely not an image payload';
        Storage::disk('public')->put($path, $garbage);

        (new CompressImageJob('public', $path, 'banners'))->handle();

        $this->assertSame($garbage, Storage::disk('public')->get($path));
    }

    public function test_a_small_existing_webp_is_left_untouched(): void
    {
        Storage::fake('public');
        // Tiny solid webp, already under target.
        $img = imagecreatetruecolor(40, 40);
        ob_start();
        imagewebp($img, null, 80);
        $webp = (string) ob_get_clean();
        imagedestroy($img);
        $path = 'avatars/tiny.png';
        Storage::disk('public')->put($path, $webp);

        (new CompressImageJob('public', $path, 'avatars'))->handle();

        $this->assertSame($webp, Storage::disk('public')->get($path));
    }

    public function test_store_public_queues_compression_for_a_raster_image(): void
    {
        Storage::fake('public');
        Queue::fake();
        config(['filesystems.disks.wasabi.key' => null]);

        MediaStorage::storePublic(UploadedFile::fake()->image('hero.jpg', 1200, 800), 'banners');

        Queue::assertPushed(CompressImageJob::class, fn ($job) => $job->context === 'banners' && $job->disk === 'public');
    }

    public function test_store_public_does_not_queue_compression_for_svg_or_excluded_contexts(): void
    {
        Storage::fake('public');
        Queue::fake();
        config(['filesystems.disks.wasabi.key' => null]);

        // SVG — sanitized, never rasterized/compressed.
        MediaStorage::storePublic(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>'), 'brand');
        // KYC/documents — deliberately excluded (§3.3).
        MediaStorage::storePublic(UploadedFile::fake()->image('id.jpg', 1000, 700), 'documents');

        Queue::assertNotPushed(CompressImageJob::class);
    }

    public function test_context_dimensions_and_compressibility_rules(): void
    {
        $this->assertSame(512, MediaStorage::contextMaxDimension('avatars'));
        $this->assertSame(1600, MediaStorage::contextMaxDimension('banners'));
        $this->assertSame(1600, MediaStorage::contextMaxDimension('anything-unmapped'));

        $this->assertTrue(MediaStorage::shouldCompress('jpg', 'banners'));
        $this->assertFalse(MediaStorage::shouldCompress('svg', 'banners')); // not a raster
        $this->assertFalse(MediaStorage::shouldCompress('gif', 'banners')); // animation-safe skip
        $this->assertFalse(MediaStorage::shouldCompress('jpg', 'documents')); // KYC excluded
    }
}
