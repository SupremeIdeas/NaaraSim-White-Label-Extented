<?php

namespace Tests\Concerns;

use App\Services\Payouts\Extensions\PayoutRailExtensions;
use App\Services\Payouts\PayoutService;
use Illuminate\Support\Facades\File;

/**
 * Installs a stand-in for an updater-delivered rail (Payoneer/Grey/Stripe Global are "coming soon" in the real app),
 * so tests of the guide/radar can exercise a global rail that is actually offerable.
 */
trait InstallsFakeRail
{
    private ?string $fakeRailDir = null;

    protected function installFakeRail(string $provider = 'payoneer'): void
    {
        $this->fakeRailDir ??= sys_get_temp_dir().'/fake-rails-'.uniqid();
        $class = 'FakeRail'.ucfirst($provider).uniqid();
        $dir = "{$this->fakeRailDir}/{$provider}";
        File::makeDirectory($dir, 0777, true, true);
        File::put("{$dir}/Gateway.php", <<<PHP
<?php
namespace App\\PayoutRails\\Fake;
use App\\Models\\PayoutAccount;
use App\\Models\\PayoutRequest;
use App\\Services\\Payouts\\{DeclaresCapabilities, PayoutEvent, PayoutGatewayInterface, PayoutTransferResult};
use Illuminate\\Http\\Request;
class {$class} implements PayoutGatewayInterface, DeclaresCapabilities {
    public function name(): string { return '{$provider}'; }
    public function available(): bool { return true; }
    public function createRecipient(PayoutAccount \$a): string { return 'r'; }
    public function sendTransfer(PayoutRequest \$r, PayoutAccount \$a): PayoutTransferResult { return new PayoutTransferResult(status: 'processing', providerRef: 'x'); }
    public function verifyWebhook(Request \$r): bool { return false; }
    public function parseWebhook(Request \$r): ?PayoutEvent { return null; }
    public function capabilities(): array { return ['confirms_synchronously' => false, 'webhook' => false, 'lookup' => false, 'cancel' => false]; }
}
PHP);
        File::put("{$dir}/rail.json", json_encode([
            'slug' => $provider, 'label' => ucfirst($provider), 'provider' => $provider, 'version' => '0.0.1',
            'gateway' => 'App\\PayoutRails\\Fake\\'.$class, 'files' => ['Gateway.php'],
        ]));
        config(['payouts.extensions_path' => $this->fakeRailDir]);
        PayoutRailExtensions::flush();
        app()->forgetInstance(PayoutService::class);
    }

    protected function removeFakeRails(): void
    {
        $this->fakeRailDir && File::deleteDirectory($this->fakeRailDir);
        $this->fakeRailDir = null;
        PayoutRailExtensions::flush();
    }
}
