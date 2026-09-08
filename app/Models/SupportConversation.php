<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    protected $fillable = [
        'user_id', 'title', 'escalated', 'escalation_reason', 'escalated_at',
        'status', 'assigned_to', 'priority', 'last_human_reply_at', 'autopilot_log',
    ];

    protected function casts(): array
    {
        return [
            'escalated' => 'boolean',
            'escalated_at' => 'datetime',
            'last_human_reply_at' => 'datetime',
            'autopilot_log' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }
}
