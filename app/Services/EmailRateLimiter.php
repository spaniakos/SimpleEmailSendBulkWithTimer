<?php

namespace App\Services;

use App\Models\EmailRecipient;
use App\Models\EmailSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmailRateLimiter
{
    /**
     * Atomically reserve one send slot against both account and destination-domain limits.
     * Reservations are conservative: failed SMTP attempts count for the window too.
     *
     * @return array{allowed: bool, retry_after: int, global_limited: bool}
     */
    public function reserve(EmailRecipient $recipient): array
    {
        $userId = (int) $recipient->emailJob->user_id;
        $domain = strtolower(substr(strrchr($recipient->email_address, '@') ?: '@unknown', 1));
        $now = now();

        return DB::transaction(function () use ($recipient, $userId, $domain, $now): array {
            // Serialize reservations and settings changes for one account across queue workers.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            $settings = EmailSettings::firstOrCreate(
                ['user_id' => $userId],
                array_merge(
                    ['unsubscribe_footer_text' => EmailSettings::DEFAULT_UNSUBSCRIBE_FOOTER_TEXT],
                    EmailSettings::rateLimitDefaults(),
                ),
            );

            $retentionSeconds = max($settings->window_seconds, $settings->domain_window_seconds);
            $events = DB::table('email_send_rate_events');
            $events->where('user_id', $userId)
                ->where('reserved_at', '<', $now->copy()->subSeconds($retentionSeconds))
                ->delete();

            $globalSince = $now->copy()->subSeconds($settings->window_seconds);
            $domainSince = $now->copy()->subSeconds($settings->domain_window_seconds);

            $globalEvents = DB::table('email_send_rate_events')->where('user_id', $userId)
                ->where('reserved_at', '>=', $globalSince);
            $domainEvents = DB::table('email_send_rate_events')->where('user_id', $userId)
                ->where('recipient_domain', $domain)
                ->where('reserved_at', '>=', $domainSince);

            $globalLimited = $globalEvents->count() >= $settings->max_emails_per_window;
            $domainLimited = $domainEvents->count() >= $settings->max_emails_per_domain;

            if ($globalLimited || $domainLimited) {
                $retryAfter = 0;

                if ($globalLimited) {
                    $first = DB::table('email_send_rate_events')->where('user_id', $userId)
                        ->where('reserved_at', '>=', $globalSince)
                        ->oldest('reserved_at')
                        ->value('reserved_at');
                    $retryAfter = max($retryAfter, $this->secondsUntilExpiry($first, $settings->window_seconds, $now));
                }

                if ($domainLimited) {
                    $first = DB::table('email_send_rate_events')->where('user_id', $userId)
                        ->where('recipient_domain', $domain)
                        ->where('reserved_at', '>=', $domainSince)
                        ->oldest('reserved_at')
                        ->value('reserved_at');
                    $retryAfter = max($retryAfter, $this->secondsUntilExpiry($first, $settings->domain_window_seconds, $now));
                }

                return [
                    'allowed' => false,
                    'retry_after' => max(1, $retryAfter),
                    'global_limited' => $globalLimited,
                ];
            }

            DB::table('email_send_rate_events')->insert([
                'user_id' => $userId,
                'email_recipient_id' => $recipient->id,
                'recipient_domain' => $domain,
                'reserved_at' => $now,
            ]);

            return ['allowed' => true, 'retry_after' => 0, 'global_limited' => false];
        }, 3);
    }

    private function secondsUntilExpiry(?string $reservedAt, int $windowSeconds, Carbon $now): int
    {
        if ($reservedAt === null) {
            return 1;
        }

        return max(1, (int) $now->diffInSeconds(Carbon::parse($reservedAt)->addSeconds($windowSeconds), false));
    }
}
