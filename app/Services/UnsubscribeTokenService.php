<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\UnsubscribeToken;
use Illuminate\Support\Facades\DB;

class UnsubscribeTokenService
{
    public function issue(Contact $contact): string
    {
        $token = bin2hex(random_bytes(32));

        $contact->unsubscribeTokens()->create([
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    public function isValid(string $token): bool
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }

        return UnsubscribeToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->whereHas('contact', fn ($query) => $query->whereNull('unsubscribed_at'))
            ->exists();
    }

    public function consume(string $token): bool
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }

        return DB::transaction(function () use ($token): bool {
            $unsubscribeToken = UnsubscribeToken::query()
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();

            if (! $unsubscribeToken) {
                return false;
            }

            $contact = Contact::query()->whereKey($unsubscribeToken->contact_id)->lockForUpdate()->first();
            if (! $contact || $contact->unsubscribed_at !== null) {
                return false;
            }

            $unsubscribeToken->update(['used_at' => now()]);
            $contact->update(['unsubscribed_at' => now()]);

            return true;
        });
    }
}
