<?php

namespace Tests\Feature;

use App\Services\Updater\PackageBuilder;
use App\Services\Updater\PackageVerifier;
use App\Support\UpdateManifest;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Batch 1 — package format + signing. These prove the trust layer end to end:
 * a genuine package round-trips, and every tamper the design must catch
 * (payload bytes, the manifest itself, a mismatched key) is caught rather than
 * silently passing.
 */
class UpdatePackageFormatTest extends TestCase
{
    private string $workDir;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = storage_path('app/testing/update-'.uniqid());
        File::ensureDirectoryExists($this->workDir);

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.product_identifier', 'naarasim-core');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);
        parent::tearDown();
    }

    private function buildSamplePackage(array $overrides = []): string
    {
        return app(PackageBuilder::class)->build(array_merge([
            'output_dir' => $this->workDir,
            'private_key' => $this->keys['private'],
            'changelog' => 'Adds a dummy service and a dummy migration.',
            'files' => [
                ['path' => 'app/Services/Dummy/DummyService.php', 'action' => 'add', 'content' => "<?php\n// dummy\n"],
                ['path' => 'database/migrations/2026_09_08_000000_create_dummy_table.php', 'action' => 'add', 'content' => "<?php\n// migration\n"],
            ],
        ], $overrides));
    }

    public function test_a_genuine_package_round_trips_and_verifies(): void
    {
        $path = $this->buildSamplePackage();

        $this->assertFileExists($path);

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertTrue($result->passed, $result->reason);
        $this->assertInstanceOf(UpdateManifest::class, $result->manifest);
        $this->assertSame('naarasim-core', $result->manifest->product);
        $this->assertTrue(UpdateManifest::isValidVersion($result->manifest->version));
        // The migration is checksummed as a payload file AND listed by name.
        $this->assertCount(2, $result->manifest->files);
        $this->assertSame(['2026_09_08_000000_create_dummy_table.php'], $result->manifest->migrations);
        $this->assertSame('code_and_migrations', $result->manifest->packageType);
    }

    public function test_a_corrupted_payload_file_fails_checksum(): void
    {
        $path = $this->buildSamplePackage();

        // Flip the bytes of a payload file AFTER signing — the manifest's
        // recorded checksum no longer matches.
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $name = PackageVerifier::PAYLOAD_PREFIX.'app/Services/Dummy/DummyService.php';
        $zip->deleteName($name);
        $zip->addFromString($name, "<?php\n// TAMPERED\n");
        $zip->close();

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Checksum mismatch', $result->reason);
    }

    public function test_editing_the_manifest_after_signing_fails_the_signature(): void
    {
        $path = $this->buildSamplePackage();

        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['version'] = '2099.01.01-9'; // bump the version, leave the old signature
        $zip->deleteName('manifest.json');
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Signature verification failed', $result->reason);
    }

    public function test_a_mismatched_public_key_fails_gracefully(): void
    {
        $path = $this->buildSamplePackage();

        // Simulate a white-label instance whose configured public key does not
        // match the key the package was signed with — a clean rejection, not a crash.
        config()->set('updater.public_key', PackageBuilder::generateKeypair()['public']);

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Signature verification failed', $result->reason);
    }

    public function test_a_missing_public_key_is_reported_not_thrown(): void
    {
        $path = $this->buildSamplePackage();
        config()->set('updater.public_key', null);

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('public key', $result->reason);
    }

    public function test_a_package_with_no_signature_is_rejected(): void
    {
        $path = $this->buildSamplePackage();

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->deleteName('signature.sig');
        $zip->close();

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('unsigned', $result->reason);
    }

    public function test_a_path_traversal_entry_is_rejected(): void
    {
        $path = $this->buildSamplePackage();

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('payload/../../../etc/evil', 'pwned');
        $zip->close();

        $result = app(PackageVerifier::class)->verify($path);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('unsafe archive entry', $result->reason);
    }

    public function test_building_an_empty_package_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PackageBuilder::class)->build([
            'output_dir' => $this->workDir,
            'private_key' => $this->keys['private'],
            'files' => [],
            'deletions' => [],
        ]);
    }

    public function test_building_without_a_private_key_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PackageBuilder::class)->build([
            'output_dir' => $this->workDir,
            'files' => [['path' => 'a.php', 'content' => 'x']],
        ]);
    }

    public function test_version_comparison_orders_by_date_then_sequence(): void
    {
        $this->assertLessThan(0, UpdateManifest::compareVersions('2026.08.15-10', '2026.09.01-2'));
        $this->assertGreaterThan(0, UpdateManifest::compareVersions('2026.09.01-2', '2026.09.01-1'));
        $this->assertSame(0, UpdateManifest::compareVersions('2026.09.01-1', '2026.09.01-1'));
    }
}
