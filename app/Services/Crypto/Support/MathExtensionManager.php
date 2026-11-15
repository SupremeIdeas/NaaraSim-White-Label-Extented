<?php

namespace App\Services\Crypto\Support;

use App\Support\Installer;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * The big-number PHP extensions that wallet verification needs (gmp, or bcmath as the slower alternative), and a one-click installer for hosts that allow it,
 * in the same spirit as the ffmpeg installer.
 *
 * What can honestly be automated: on a server where PHP runs as root, or may use passwordless sudo, and the system has apt or dnf, we install the package, reload PHP
 * and prove it. On shared hosting (cPanel, Plesk, managed) no web app can install a PHP extension: there, status() returns the exact clicks for that panel instead. Nothing
 * user-typed ever reaches a command: the only inputs are the PHP major.minor digits, checked against a strict pattern.
 */
class MathExtensionManager
{
    private const STATE = 'crypto.math.install.v1';

    public static function logPath(): string
    {
        return storage_path('app/crypto-math-install.log');
    }

    /** gmp or bcmath is loaded in THIS process. */
    public static function loaded(): bool
    {
        return extension_loaded('gmp') || extension_loaded('bcmath');
    }

    /** @return array{ok: bool, gmp: bool, bcmath: bool, php: string, sapi: string, os: string, ini: ?string, exec: bool, root: bool, sudo: bool, manager: ?string, can_install: bool, blocker: ?string, steps: list<string>} */
    public function status(): array
    {
        $exec = $this->execAllowed();
        $root = function_exists('posix_geteuid') && @posix_geteuid() === 0;
        $sudo = ! $root && $exec && $this->run(['sudo', '-n', 'true'], 8)['ok'];
        $manager = $exec ? $this->packageManager() : null;
        $mm = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $blocker = match (true) {
            ! $exec => 'This host blocks running programs (proc_open and exec are disabled), so the installer cannot run here.',
            PHP_OS_FAMILY !== 'Linux' => 'One-click install works on Linux servers only.',
            $manager === null => 'No supported package manager (apt or dnf) was found. Use the steps below.',
            ! $root && ! $sudo => 'PHP does not run as an administrator here, which is normal and safer. Use the steps below, or run the one command over SSH.',
            default => null,
        };
        $steps = $manager === 'apt'
            ? ["Over SSH, as an administrator, run: sudo apt-get install -y php{$mm}-gmp php{$mm}-bcmath", "Then reload PHP: sudo systemctl reload php{$mm}-fpm (or: sudo systemctl reload apache2)"]
            : ($manager === 'dnf' ? ['Over SSH, as an administrator, run: sudo dnf install -y php-gmp php-bcmath', 'Then reload PHP: sudo systemctl reload php-fpm (or httpd)'] : []);
        $steps[] = 'cPanel: Software, then Select PHP Version (or MultiPHP / "Extensions"), tick gmp and bcmath, Save.';
        $steps[] = 'Plesk: Tools & Settings, PHP Settings (or the domain\'s PHP Settings), enable gmp and bcmath.';
        $steps[] = 'Not sure? Send your host this line: "Please enable the PHP gmp and bcmath extensions for PHP '.$mm.'."';

        return ['ok' => self::loaded(), 'gmp' => extension_loaded('gmp'), 'bcmath' => extension_loaded('bcmath'), 'php' => $mm, 'sapi' => PHP_SAPI, 'os' => PHP_OS_FAMILY,
            'ini' => php_ini_loaded_file() ?: null, 'exec' => $exec, 'root' => $root, 'sudo' => $sudo, 'manager' => $manager, 'can_install' => $blocker === null, 'blocker' => $blocker, 'steps' => $steps];
    }

    /** Start the install in the background (so it survives the request and a short PHP time limit). @return array{state: string, log: string} */
    public function start(): array
    {
        $st = $this->status();
        if (! $st['can_install']) {
            return ['state' => 'failed', 'log' => (string) $st['blocker']];
        }
        if (preg_match('/^\d\.\d{1,2}$/', $st['php']) !== 1) {
            return ['state' => 'failed', 'log' => 'Unexpected PHP version.'];
        }
        $mm = $st['php'];
        $p = $st['root'] ? '' : 'sudo -n ';
        $script = $st['manager'] === 'apt'
            ? "export DEBIAN_FRONTEND=noninteractive\n{$p}apt-get update -qq\n{$p}apt-get install -y php{$mm}-gmp php{$mm}-bcmath || { echo 'DONE-FAIL: the package could not be installed'; exit 1; }\n"
            : "{$p}dnf install -y php-gmp php-bcmath || { echo 'DONE-FAIL: the package could not be installed'; exit 1; }\n";
        $script .= "echo 'Reloading PHP so the website picks it up...'\n{$p}systemctl reload php{$mm}-fpm 2>/dev/null || {$p}systemctl reload php-fpm 2>/dev/null || true\n{$p}systemctl reload apache2 2>/dev/null || {$p}systemctl reload httpd 2>/dev/null || true\necho 'DONE-OK'\n";
        @mkdir(dirname(self::logPath()), 0775, true);
        $file = storage_path('app/crypto-math-install.sh');
        file_put_contents($file, "#!/bin/sh\n{$script}");
        file_put_contents(self::logPath(), "Installing the PHP gmp and bcmath extensions for PHP {$mm}...\n");
        Cache::put(self::STATE, ['at' => now()->timestamp], 900);
        Process::fromShellCommandline('nohup sh '.escapeshellarg($file).' >> '.escapeshellarg(self::logPath()).' 2>&1 &')->run();

        return ['state' => 'running', 'log' => 'started'];
    }

    /** @return array{state: string, log: string, verified: bool} */
    public function poll(): array
    {
        $log = is_file(self::logPath()) ? (string) @file_get_contents(self::logPath()) : '';
        $state = str_contains($log, 'DONE-OK') ? 'done' : (str_contains($log, 'DONE-FAIL') ? 'failed' : (Cache::has(self::STATE) ? 'running' : 'idle'));
        $verified = false;
        if ($state === 'done') {
            // A fresh CLI process sees the new extension even while this web worker still has the old list.
            $r = $this->run([Installer::phpBinary(), '-r', 'echo (extension_loaded("gmp")||extension_loaded("bcmath"))?"yes":"no";'], 20);
            $verified = $r['ok'] && trim($r['out']) === 'yes';
        }

        return ['state' => $state, 'log' => mb_substr(trim($log), -1500), 'verified' => $verified];
    }

    private function packageManager(): ?string
    {
        foreach (['apt-get' => 'apt', 'dnf' => 'dnf'] as $bin => $name) {
            foreach (['/usr/bin/', '/bin/', '/usr/sbin/'] as $dir) {
                if (@is_executable($dir.$bin)) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function execAllowed(): bool
    {
        $dis = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $dis, true);
    }

    /** @param list<string> $cmd @return array{ok: bool, out: string} */
    private function run(array $cmd, int $timeout): array
    {
        try {
            $p = new Process($cmd);
            $p->setTimeout($timeout);
            $p->run();

            return ['ok' => $p->isSuccessful(), 'out' => $p->getOutput()];
        } catch (\Throwable) {
            return ['ok' => false, 'out' => ''];
        }
    }
}
