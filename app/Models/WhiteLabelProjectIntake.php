<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prompt 21-EXT2 §2 — the project-commencement brief for one white-label
 * instance. Credential fields (`hosting_host`/`hosting_username`/
 * `hosting_password`/`hosting_notes`) are `encrypted` casts — same discipline
 * as `PortInRequest::account_number`/`pin` — so they never sit in plaintext
 * at rest. They are populated only for a self-hosted `hosting_choice` and are
 * decrypted only for the admin intake-detail view and the PDF export.
 */
class WhiteLabelProjectIntake extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SEEN = 'seen';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'white_label_instance_id',
        'desired_brand_name',
        'whatsapp_number',
        'brand_primary_color',
        'brand_accent_color',
        'logo_url',
        'logo_design_reference',
        'banner_reference_url',
        'banner_design_request',
        'hosting_choice',
        'hosting_disclaimer_acknowledged_at',
        'hosting_host',
        'hosting_username',
        'hosting_password',
        'hosting_notes',
        'additional_notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'deploy_days',
        'deploy_started_at',
        'deploy_completed_at',
    ];

    protected $hidden = [
        'hosting_host',
        'hosting_username',
        'hosting_password',
        'hosting_notes',
    ];

    protected function casts(): array
    {
        return [
            'hosting_disclaimer_acknowledged_at' => 'datetime',
            'hosting_host' => 'encrypted',
            'hosting_username' => 'encrypted',
            'hosting_password' => 'encrypted',
            'hosting_notes' => 'encrypted',
            'reviewed_at' => 'datetime',
            'deploy_started_at' => 'datetime',
            'deploy_completed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhiteLabelInstance::class, 'white_label_instance_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isSelfHosted(): bool
    {
        return in_array($this->hosting_choice, [
            \App\Models\WhiteLabelInstance::HOSTING_OWN_VPS,
            \App\Models\WhiteLabelInstance::HOSTING_OWN_SHARED,
        ], true);
    }
}
