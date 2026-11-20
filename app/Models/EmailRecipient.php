<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_job_id',
        'contact_id',
        'email_address',
        'domain',
        'status',
        'sent_at',
        'error_message',
        'suppressed_at',
        'removed_at',
        'open_tracking_token_hash',
        'opened_at',
        'message_id',
        'delivery_status',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'suppressed_at' => 'datetime',
        'removed_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function emailJob(): BelongsTo
    {
        return $this->belongsTo(EmailJob::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(EmailDeliveryEvent::class)->orderBy('occurred_at');
    }
}
