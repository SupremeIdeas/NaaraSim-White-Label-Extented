<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One segment of the floating navigation pill (Homepage floating-nav prompt §2).
 * Admin-assignable: repoint any slot to any page/feature, choose who sees it, and
 * mark one as the glowing centerpiece.
 */
class NavSlot extends Model
{
    public const VISIBILITIES = ['all', 'auth', 'guest'];

    /** Sentinel target that opens the in-page NaaraSim wizard instead of navigating. */
    public const TARGET_WIZARD = 'wizard';

    protected $fillable = [
        'position', 'label', 'icon', 'target', 'visibility', 'is_center', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_center' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function opensWizard(): bool
    {
        return $this->target === self::TARGET_WIZARD;
    }

    /** Whether this slot should show for the current auth state. */
    public function visibleTo(bool $authed): bool
    {
        return match ($this->visibility) {
            'auth' => $authed,
            'guest' => ! $authed,
            default => true,
        };
    }
}
