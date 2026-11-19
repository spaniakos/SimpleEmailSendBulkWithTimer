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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
