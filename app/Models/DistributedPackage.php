<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A package the master platform makes available to white-label instances
 * (Batch 4 §4). Built (Batch 1) and published are two deliberate steps —
 * `is_published` gates whether the distribution check endpoint will ever
 * mention this package.
 */
class DistributedPackage extends Model
{
    /** Package types that count as "code" updates (everything that isn't a theme). */
    public const CODE_TYPES = ['code', 'code_and_migrations', 'migrations'];

    public const THEME_TYPE = 'theme';

    protected $fillable = [
        'package_id',
        'product',
        'version',
        'package_type',
        'min_compatible_version',
        'tier_requirement',
        'changelog',
        'storage_path',
        'size_bytes',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function isTheme(): bool
    {
        return $this->package_type === self::THEME_TYPE;
    }
}
