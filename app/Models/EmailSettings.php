<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSettings extends Model
{
    public const DEFAULT_UNSUBSCRIBE_FOOTER_TEXT = 'You are receiving this email because you are on this mailing list.';

    protected $fillable = [
        'user_id',
        'unsubscribe_footer_text',
        'max_emails_per_window',
        'window_seconds',
        'max_emails_per_domain',
        'domain_window_seconds',
    ];

    protected $casts = [
        'max_emails_per_window' => 'integer',
        'window_seconds' => 'integer',
        'max_emails_per_domain' => 'integer',
        'domain_window_seconds' => 'integer',
    ];

    public static function rateLimitDefaults(): array
    {
        $legacyCount = max(1, (int) config('email.rate_limit.emails_per_domain', 1));
        $legacySeconds = max(1, (int) config('email.rate_limit.seconds', 10));

        return [
            'max_emails_per_window' => max(1, (int) config('email.rate_limit.max_emails_per_window', 6)),
            'window_seconds' => max(1, (int) config('email.rate_limit.window_seconds', 60)),
            'max_emails_per_domain' => max(1, (int) config('email.rate_limit.max_emails_per_domain', $legacyCount)),
            'domain_window_seconds' => max(1, (int) config('email.rate_limit.domain_window_seconds', $legacySeconds)),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
