<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSendRateEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email_recipient_id',
        'recipient_domain',
        'reserved_at',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(EmailRecipient::class, 'email_recipient_id');
    }
}
