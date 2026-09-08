<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-template admin override for a transactional email (Email Studio §3.1).
 * Only non-null fields override the Blade default.
 */
class MailTemplateOverride extends Model
{
    protected $fillable = [
        'template_key', 'subject', 'heading', 'intro', 'button_text', 'accent_color',
    ];
}
