<?php

namespace App\Support;

/** Read side of config/tested_providers.php: which providers were exercised with real keys, and how far. */
class TestedProviders
{
    /** @return ?array{level: string, on: string, tested: string, not_yet: string} */
    public static function for(?string $provider): ?array
    {
        $p = strtolower((string) $provider);
        $row = config('tested_providers.'.$p);

        return is_array($row) && in_array($row['level'] ?? null, ['proven', 'connected'], true) ? $row : null;
    }

    public static function recommended(?string $provider): bool
    {
        return (self::for($provider)['level'] ?? null) === 'proven';
    }

    /** @return list<string> provider slugs, proven first */
    public static function all(): array
    {
        $rows = (array) config('tested_providers', []);
        uasort($rows, fn ($a, $b) => ($a['level'] === 'proven' ? 0 : 1) <=> ($b['level'] === 'proven' ? 0 : 1));

        return array_keys($rows);
    }
}
