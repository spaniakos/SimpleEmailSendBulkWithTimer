<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_address',
        'unsubscribed_at',
        'delivery_suppressed_at',
        'delivery_suppression_reason',
    ];

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
            'delivery_suppressed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mailingLists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'mailing_list_contacts')->withTimestamps();
    }

    public function unsubscribeTokens(): HasMany
    {
        return $this->hasMany(UnsubscribeToken::class);
    }
}
