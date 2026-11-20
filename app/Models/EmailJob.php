<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'smtp_configuration_id',
        'mailing_list_id',
        'name',
        'subject',
        'html_content',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'suppressed_count',
        'track_opens',
    ];

    protected $casts = [
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'suppressed_count' => 'integer',
        'track_opens' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function smtpConfiguration(): BelongsTo
    {
        return $this->belongsTo(SmtpConfiguration::class);
    }

    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(MailingList::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailRecipient::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        $completed = $this->sent_count + $this->failed_count + $this->suppressed_count;

        return round(($completed / $this->total_recipients) * 100, 2);
    }
}
