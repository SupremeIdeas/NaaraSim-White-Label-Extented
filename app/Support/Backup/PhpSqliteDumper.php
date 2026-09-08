<?php

namespace App\Support\Backup;

use PDO;
use Spatie\DbDumper\Databases\Sqlite;

/**
 * A pure-PHP SQLite dumper (blueprint Section 28). Spatie's native SQLite
 * dumper shells out to the `sqlite3` binary, which many restricted hosts (and
 * this build sandbox) don't ship. This produces a real, restorable .sql text
 * dump straight from PDO — schema + data + indexes — with no CLI binary.
 */
class PhpSqliteDumper extends Sqlite
{
    public function dumpToFile(string $dumpFile): void
    {
        $pdo = new PDO('sqlite:'.$this->dbName);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $handle = fopen($dumpFile, 'w');
        fwrite($handle, "PRAGMA foreign_keys=OFF;\n");

        $tables = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND sql IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tables as $table) {
            fwrite($handle, "DROP TABLE IF EXISTS \"{$table['name']}\";\n");
            fwrite($handle, $table['sql'].";\n");

            $rows = $pdo->query('SELECT * FROM "'.$table['name'].'"');
            foreach ($rows as $row) {
                $columns = '"'.implode('","', array_keys($row)).'"';
                $values = implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values($row)
                ));
                fwrite($handle, "INSERT INTO \"{$table['name']}\" ({$columns}) VALUES ({$values});\n");
            }
        }

        $indexes = $pdo->query(
            "SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($indexes as $sql) {
            fwrite($handle, $sql.";\n");
        }

        fclose($handle);
    }
}
