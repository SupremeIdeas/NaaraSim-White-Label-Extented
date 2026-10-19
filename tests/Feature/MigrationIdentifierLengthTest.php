<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** MySQL refuses identifiers over 64 characters (error 1059) and an installer run dies half way. Every index, unique and foreign key name across all migrations must fit. */
class MigrationIdentifierLengthTest extends TestCase
{
    public function test_every_index_and_foreign_key_name_fits_in_mysql(): void
    {
        $names = new \ArrayObject;
        $this->app->bind(Blueprint::class, function ($app, $p) use ($names) {
            return new class($p['connection'], $p['table'], $p['callback'] ?? null, $names) extends Blueprint
            {
                public function __construct($connection, $table, $callback, private \ArrayObject $seen)
                {
                    parent::__construct($connection, $table, $callback);
                }

                protected function createIndexName($type, array $columns)
                {
                    $n = parent::createIndexName($type, $columns);
                    $this->seen[] = [$this->table, $n];

                    return $n;
                }

                public function index($columns, $name = null, $algorithm = null)
                {
                    if ($name) {
                        $this->seen[] = [$this->table, $name];
                    }

                    return parent::index($columns, $name, $algorithm);
                }

                public function unique($columns, $name = null, $algorithm = null)
                {
                    if ($name) {
                        $this->seen[] = [$this->table, $name];
                    }

                    return parent::unique($columns, $name, $algorithm);
                }
            };
        });

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertGreaterThan(100, count($names), 'the probe saw the migrations');
        $bad = collect($names)->filter(fn ($r) => strlen($r[1]) > 64)->map(fn ($r) => strlen($r[1]).' '.$r[0].' '.$r[1])->values()->all();
        $this->assertSame([], $bad, "Name these explicitly (max 64 characters):\n".implode("\n", $bad));
    }
}
