<?php

namespace Tests\Feature;

use App\Livewire\Admin\PreloaderStudio;
use App\Models\User;
use App\Support\CustomPreloader;
use App\Support\MediaStorage;
use App\Support\PreloaderSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Preloader Studio → "Your own animation": GIF / WebP / Lottie JSON per light + dark mode. */
class CustomPreloaderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function gif(string $name = 'a.gif'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;");
    }

    private function webp(string $name = 'a.webp'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'RIFF'."\x20\x00\x00\x00".'WEBPVP8X'."\x0a\x00\x00\x00\x12\x00\x00\x00\x00\x00\x00\x00\x00\x00ANIM");
    }

    private function lottieJson(array $over = []): string
    {
        return json_encode(array_merge([
            'v' => '5.7.0', 'fr' => 30, 'ip' => 0, 'op' => 60, 'w' => 100, 'h' => 100,
            'layers' => [['ty' => 4, 'nm' => 'dot', 'ks' => ['o' => ['a' => 0, 'k' => 100]]]],
        ], $over));
    }

    private function lottie(array $over = [], string $name = 'a.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->lottieJson($over));
    }

    public function test_sniff_identifies_files_by_content_not_extension(): void
    {
        $this->assertSame('gif', CustomPreloader::sniff("GIF89a....."));
        $this->assertSame('webp', CustomPreloader::sniff('RIFF'."\x00\x00\x00\x00".'WEBPVP8 '));
        $this->assertSame('lottie', CustomPreloader::sniff($this->lottieJson()));
        $this->assertNull(CustomPreloader::sniff('<?php echo 1;'));
        $this->assertNull(CustomPreloader::sniff('{"hello":"world"}'));
        $this->assertNull(CustomPreloader::sniff('<svg xmlns="http://www.w3.org/2000/svg"></svg>'));
    }

    public function test_lottie_with_expressions_or_remote_assets_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CustomPreloader::assertSafeLottie($this->lottieJson(['layers' => [['ty' => 4, 'ks' => ['p' => ['x' => 'time*10']]]]]));
    }

    public function test_lottie_with_remote_url_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CustomPreloader::assertSafeLottie($this->lottieJson(['assets' => [['id' => 'i', 'p' => 'https://evil.test/x.png', 'u' => '']]]));
    }

    public function test_safe_lottie_with_embedded_asset_passes(): void
    {
        CustomPreloader::assertSafeLottie($this->lottieJson(['assets' => [['id' => 'i', 'p' => 'data:image/png;base64,AAAA', 'u' => '', 'e' => 1]]]));
        $this->assertTrue(true);
    }

    public function test_resolved_falls_back_to_the_other_mode(): void
    {
        $meta = ['kind' => 'gif', 'path' => 'preloaders/x.gif', 'disk' => 'public', 'id' => 'x'];
        $this->assertSame($meta, CustomPreloader::resolved(['light' => $meta])['dark']);
        $this->assertSame($meta, CustomPreloader::resolved(['dark' => $meta])['light']);
        $this->assertSame([null, null], array_values(CustomPreloader::resolved([])));
    }

    public function test_clean_drops_unknown_kinds_variants_and_foreign_paths(): void
    {
        $out = CustomPreloader::clean([
            'light' => ['kind' => 'exe', 'path' => 'preloaders/a.exe'],
            'dark' => ['kind' => 'gif', 'path' => '../../etc/passwd'],
            'sepia' => ['kind' => 'gif', 'path' => 'preloaders/a.gif'],
        ]);
        $this->assertSame([], $out);
    }

    public function test_admin_uploads_per_mode_saves_and_the_assignment_resolves(): void
    {
        Storage::fake(MediaStorage::disk());
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(PreloaderStudio::class)
            ->set('uploadLight', $this->gif())
            ->assertSet('preset', 'custom')
            ->assertSet('customError', null)
            ->set('uploadDark', $this->webp())
            ->assertSet('customError', null)
            ->call('save')
            ->assertHasNoErrors();

        $cfg = PreloaderSettings::forPageType('default');
        $this->assertSame('custom', $cfg['preset']);
        $this->assertSame('gif', $cfg['custom']['light']['kind']);
        $this->assertSame('webp', $cfg['custom']['dark']['kind']);
        Storage::disk(MediaStorage::disk())->assertExists($cfg['custom']['light']['path']);
        Storage::disk(MediaStorage::disk())->assertExists($cfg['custom']['dark']['path']);
    }

    public function test_a_non_animation_upload_is_refused_even_with_a_gif_extension(): void
    {
        Storage::fake(MediaStorage::disk());

        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->set('uploadLight', UploadedFile::fake()->createWithContent('evil.gif', '<?php system($_GET["c"]);'))
            ->assertSet('custom', [])
            ->assertNotSet('customError', null);

        $this->assertSame([], Storage::disk(MediaStorage::disk())->allFiles(CustomPreloader::DIR));
    }

    public function test_an_oversized_gif_is_refused(): void
    {
        Storage::fake(MediaStorage::disk());
        $big = UploadedFile::fake()->createWithContent('big.gif', "GIF89a".str_repeat('0', 1100 * 1024));

        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->set('uploadDark', $big)
            ->assertSet('custom', [])
            ->assertNotSet('customError', null);
    }

    public function test_saving_custom_without_a_file_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->call('selectPreset', 'custom')
            ->call('save')
            ->assertHasErrors('preset');
    }

    public function test_replace_removes_the_unsaved_file_and_removing_after_save_frees_it(): void
    {
        Storage::fake(MediaStorage::disk());
        $disk = Storage::disk(MediaStorage::disk());

        $c = Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->set('uploadLight', $this->gif('one.gif'));
        $first = $c->get('custom')['light']['path'];
        $c->set('uploadLight', $this->gif('two.gif'));
        $second = $c->get('custom')['light']['path'];

        $this->assertNotSame($first, $second);
        $disk->assertMissing($first);
        $disk->assertExists($second);

        $c->call('save');
        $disk->assertExists($second);

        // Drop dark+light → switch back to a preset → the saved file is released.
        $c->call('removeCustom', 'light')->call('selectPreset', 'simple-pulse')->call('save');
        $disk->assertMissing($second);
        $this->assertSame('simple-pulse', PreloaderSettings::forPageType('default')['preset']);
    }

    public function test_lottie_is_served_from_our_own_origin_and_unknown_ids_404(): void
    {
        Storage::fake(MediaStorage::disk());

        $c = Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->set('uploadDark', $this->lottie());
        $this->assertSame('lottie', $c->get('custom')['dark']['kind']);
        $id = $c->get('custom')['dark']['id'];

        $this->get('/preloader-asset/'.$id.'.json')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('fr', 30);

        $this->get('/preloader-asset/'.\Illuminate\Support\Str::uuid().'.json')->assertNotFound();
        $this->get('/preloader-asset/not-a-uuid.json')->assertNotFound();
    }

    public function test_overlay_renders_both_mode_files_for_the_runtime_picker(): void
    {
        $light = ['kind' => 'gif', 'path' => 'preloaders/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.gif', 'disk' => 'public', 'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'];
        $dark = ['kind' => 'lottie', 'path' => 'preloaders/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb.json', 'disk' => 'public', 'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'];

        $html = Blade::render('@include("components.preloaders.custom", ["cfg" => $cfg])', ['cfg' => ['custom' => ['light' => $light, 'dark' => $dark]]]);

        $this->assertStringContainsString('data-light-kind="gif"', $html);
        $this->assertStringContainsString('data-dark-kind="lottie"', $html);
        $this->assertStringContainsString('/preloader-asset/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb.json', $html);
    }

    public function test_a_single_upload_serves_both_modes(): void
    {
        $dark = ['kind' => 'gif', 'path' => 'preloaders/cccccccc-cccc-cccc-cccc-cccccccccccc.gif', 'disk' => 'public', 'id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc'];

        $html = Blade::render('@include("components.preloaders.custom", ["cfg" => $cfg])', ['cfg' => ['custom' => ['dark' => $dark]]]);

        $this->assertStringContainsString('data-light-kind="gif"', $html);
        $this->assertStringContainsString('data-dark-kind="gif"', $html);
    }

    public function test_custom_preset_without_files_degrades_to_the_safe_default(): void
    {
        PreloaderSettings::saveAssignment('default', ['preset' => 'custom', 'enabled' => true, 'custom' => []]);

        $this->assertNotSame('custom', PreloaderSettings::forPageType('default')['preset']);
    }
}
