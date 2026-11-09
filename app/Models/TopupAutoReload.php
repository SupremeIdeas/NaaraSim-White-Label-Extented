<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A repeating top-up (daily / weekly / monthly), airtime or a data bundle, for one beneficiary. */
class TopupAutoReload extends Model
{
    protected $table = 'topup_autoreloads';

    public const FREQUENCIES = ['daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month'];

    protected $fillable = [
        'user_id', 'beneficiary_id', 'country_iso', 'operator_name', 'product_type', 'send_amount', 'send_currency', 'product_name', 'frequency',
        'run_hour', 'run_weekday', 'run_monthday', 'timezone', 'next_run_at', 'last_run_at', 'last_order_id', 'last_status', 'runs', 'failures',
        'active', 'paused_reason', 'low_balance_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'send_amount' => 'decimal:2', 'active' => 'boolean', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime',
            'low_balance_notified_at' => 'datetime',
        ];
    }

    /** Scheduler slot being processed (not persisted). */
    public ?int $slot = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(TopupBeneficiary::class, 'beneficiary_id');
    }

    /** The next run strictly after $from, in the schedule's own timezone, returned as an app-timezone Carbon. */
    public function computeNext(?\DateTimeInterface $from = null): \Illuminate\Support\Carbon
    {
        $tz = $this->timezone ?: 'UTC';
        $base = \Illuminate\Support\Carbon::instance($from ?? now())->setTimezone($tz);
        $c = $base->copy()->setTime((int) $this->run_hour, 0, 0);
        if ($this->frequency === 'weekly') {
            $c->addDays(((int) $this->run_weekday - $c->dayOfWeek + 7) % 7);
            if ($c->lte($base)) {
                $c->addWeek();
            }
        } elseif ($this->frequency === 'monthly') {
            $c->day(min(28, max(1, (int) $this->run_monthday)));
            if ($c->lte($base)) {
                $c->addMonthNoOverflow()->day(min(28, max(1, (int) $this->run_monthday)));
            }
        } elseif ($c->lte($base)) {
            $c->addDay();
        }

        return $c->setTimezone(config('app.timezone'));
    }

    public function describe(): string
    {
        $h = str_pad((string) $this->run_hour, 2, '0', STR_PAD_LEFT).':00';
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return match ($this->frequency) {
            'weekly' => 'Every '.($days[(int) $this->run_weekday] ?? 'week')." at $h",
            'monthly' => 'Every month on day '.(int) $this->run_monthday." at $h",
            default => "Every day at $h",
        };
    }
}
