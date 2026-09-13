<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * US/Canada port-in intake (Prompt 11). The losing-carrier secrets
 * (account_number, pin) are encrypted at rest and hidden from serialization;
 * the target provider is PRIVATE (supplier masking, money-rule 1.2). Closed
 * requests have their secrets purged — see Admin\PortInRequests.
 */
class PortInRequest extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_SUBMITTED_TO_CARRIER = 'submitted_to_carrier';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_SUBMITTED_TO_CARRIER,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'user_id',
        'phone_number',
        'status',
        'account_number',
        'pin',
        'billing_name',
        'billing_address',
        'notes',
        'admin_notes',
        'rejection_reason',
        'provider',
        'reviewed_by',
    ];

    protected $hidden = [
        'account_number',
        'pin',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'pin' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** A request is still open (customer-visible as "in progress") until it closes. */
    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_REJECTED], true);
    }

    /** Human label for a status value. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_IN_REVIEW => 'In review',
            self::STATUS_SUBMITTED_TO_CARRIER => 'Submitted to carrier',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
