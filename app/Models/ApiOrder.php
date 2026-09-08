<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Developer API order (ROADMAP §Layer 2) — the developer-facing record of a
 * purchase, priced at the developer lane. The linked esim/sms order holds the
 * internal cost/provider; this row and toApiArray() never expose either.
 */
class ApiOrder extends Model
{
    protected $fillable = [
        'api_client_id',
        'kind',
        'reference',
        'status',
        'price_usd',
        'currency',
        'esim_order_id',
        'sms_order_id',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:4',
            'result' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function esimOrder(): BelongsTo
    {
        return $this->belongsTo(EsimOrder::class, 'esim_order_id');
    }

    public function smsOrder(): BelongsTo
    {
        return $this->belongsTo(SmsOrder::class, 'sms_order_id');
    }

    /**
     * Fold the linked number order's live state into this API order (in memory,
     * for a status read): the OTP code appears once received; a timeout marks the
     * order failed. Never exposes the supplier.
     */
    public function applyNumberStatus(): void
    {
        $sms = $this->smsOrder;
        if (! $sms) {
            return;
        }
        if ($sms->status === 'completed' && $sms->otp_code) {
            $this->status = 'completed';
            $this->result = array_merge($this->result ?? [], ['code' => $sms->otp_code]);
        } elseif ($sms->status === 'timeout') {
            $this->status = 'failed';
        }
    }

    /**
     * Latest developer-facing status derived from the linked eSIM order: once
     * the eSIM is active/completed the API order reads "completed", otherwise it
     * stays at its stored status. Returns null when there's no linked eSIM.
     */
    public function esimStatus(): ?string
    {
        $esim = $this->esimOrder;
        if (! $esim) {
            return null;
        }

        return in_array($esim->status, ['active', 'completed'], true) ? 'completed' : $this->status;
    }

    /** The masked payload returned to the developer — never cost or supplier. */
    public function toApiArray(): array
    {
        return [
            'reference' => $this->reference,
            'kind' => $this->kind,
            'status' => $this->status,
            'price_usd' => round((float) $this->price_usd, 4),
            'currency' => $this->currency,
            'result' => $this->result ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
