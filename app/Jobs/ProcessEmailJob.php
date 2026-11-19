<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\EmailSettings;
use App\Models\SmtpConfiguration;
use App\Services\EmailRateLimiter;
use App\Services\UnsubscribeTokenService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessEmailJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    // Retries are ONLY for real failures (SMTP down, crash, etc)
    public $tries = 3;

    public $maxExceptions = 3;

    public function __construct(
        public EmailJob $emailJob,
        public SmtpConfiguration $smtpConfig
    ) {}

    public function handle(): void
    {
        $lockKey = "email_job_processing_{$this->emailJob->id}";
        $retryAfter = 0;
        $defaultDelay = max(1, (int) config('email.rate_limit.seconds', 10));

        // 1️⃣ Prevent Parallel Execution
        if (! Cache::add($lockKey, 'worker', 120)) {
            $this->scheduleNextRun($defaultDelay);

            return;
        }

        try {
            $this->emailJob->refresh();
            $this->smtpConfig->refresh();

            // 2️⃣ Exit early for paused / completed
            if ($this->shouldStopEarly()) {
                Cache::forget($lockKey);

                return;
            }

            // 3️⃣ Process a batch of recipients
            $pending = EmailRecipient::where('email_job_id', $this->emailJob->id)
                ->where('status', 'pending')
                ->with('emailJob')
                ->orderBy('id')
                ->lazyById(100);

            if (! EmailRecipient::where('email_job_id', $this->emailJob->id)->where('status', 'pending')->exists()) {
                $this->finishJobIfComplete();
                Cache::forget($lockKey);

                return;
            }

            foreach ($pending as $recipient) {
                if ($this->suppressIfUnsubscribed($recipient)) {
                    continue;
                }

                // Keep the campaign lease alive while scanning capped domains as well as while sending.
                Cache::put($lockKey, 'worker', 120);
                $reservation = app(EmailRateLimiter::class)->reserve($recipient);
                if (! $reservation['allowed']) {
                    $retryAfter = $retryAfter === 0
                        ? $reservation['retry_after']
                        : min($retryAfter, $reservation['retry_after']);
                    if ($reservation['global_limited']) {
                        break;
                    }

                    // A saturated destination must not block an eligible destination later in this list.
                    continue;
                }

                $this->sendToRecipient($recipient);
            }

            $this->finishJobIfComplete();

            // 4️⃣ Continue if more exist
            if ($this->hasPendingRecipients() && $this->emailJob->status !== 'paused') {
                Cache::forget($lockKey);
                $this->scheduleNextRun($retryAfter ?: $defaultDelay);

                return;
            }

            Cache::forget($lockKey);

            return;

        } catch (Exception $e) {

            Cache::forget($lockKey);

            Log::error("Fatal error in job {$this->emailJob->id}: ".$e->getMessage());

            // 5️⃣ Fail the job only for real failures
            $this->fail($e);
        }
    }

    private function shouldStopEarly(): bool
    {
        if ($this->emailJob->status === 'paused') {
            return true;
        }
        if (in_array($this->emailJob->status, ['completed', 'failed'])) {
            return true;
        }

        if ($this->emailJob->status === 'pending') {
            $this->emailJob->update(['status' => 'processing']);
        }

        return false;
    }

    private function sendToRecipient($recipient): void
    {
        try {
            if ($this->suppressIfUnsubscribed($recipient)) {
                return;
            }

            $contact = $recipient->contact;
            if ($contact === null || (int) $contact->user_id !== (int) $this->emailJob->user_id) {
                throw new \RuntimeException('Campaign recipient is not linked to a contact owned by this account.');
            }

            $this->configureMailer();
            $token = app(UnsubscribeTokenService::class)->issue($contact);
            $unsubscribeUrl = route('unsubscribe.confirm', ['token' => $token]);
            $footerText = $this->emailJob->user->emailSettings?->unsubscribe_footer_text
                ?? EmailSettings::DEFAULT_UNSUBSCRIBE_FOOTER_TEXT;

            Log::info("Sending email to {$recipient->email_address}");
            Mail::mailer('smtp')->to($recipient->email_address)->send(new CampaignEmail(
                htmlContent: $this->emailJob->html_content,
                subjectLine: $this->emailJob->subject,
                senderEmail: $this->smtpConfig->from_email,
                senderName: $this->smtpConfig->from_name,
                unsubscribeUrl: $unsubscribeUrl,
                footerText: $footerText,
            ));

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
            $this->emailJob->increment('sent_count');

        } catch (Exception $e) {
            Log::error("Failed email to {$recipient->email_address}: ".$e->getMessage());
            $recipient->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            $this->emailJob->increment('failed_count');
        }
    }

    private function suppressIfUnsubscribed(EmailRecipient $recipient): bool
    {
        $recipient->load('contact');
        $contact = $recipient->contact;

        if ($contact === null || $contact->unsubscribed_at === null) {
            return false;
        }

        if ($recipient->status !== 'suppressed') {
            $recipient->update(['status' => 'suppressed', 'suppressed_at' => now()]);
            $this->emailJob->increment('suppressed_count');
        }

        return true;
    }

    private function configureMailer(): void
    {
        config(['mail.mailers.smtp' => [
            'transport' => 'smtp',
            'host' => $this->smtpConfig->host,
            'port' => $this->smtpConfig->port,
            'encryption' => $this->smtpConfig->encryption,
            'username' => $this->smtpConfig->username,
            'password' => $this->smtpConfig->password,
            'timeout' => 20,
        ]]);
    }

    private function finishJobIfComplete(): void
    {
        $this->emailJob->refresh();
        $total = $this->emailJob->sent_count + $this->emailJob->failed_count + $this->emailJob->suppressed_count;

        if ($total >= $this->emailJob->total_recipients) {
            $this->emailJob->update([
                'status' => $this->emailJob->failed_count > 0 && $this->emailJob->sent_count === 0
                    ? 'failed'
                    : 'completed',
            ]);
        }
    }

    private function hasPendingRecipients(): bool
    {
        return EmailRecipient::where('email_job_id', $this->emailJob->id)
            ->where('status', 'pending')
            ->exists();
    }

    private function scheduleNextRun(int $baseDelay): void
    {
        $offset = mt_rand(0, 1000) / 1000;
        $delay = $baseDelay + $offset;

        Log::info("Scheduling next job run with {$delay}s delay (base: {$baseDelay}s + offset: {$offset}s).");

        ProcessEmailJob::dispatch($this->emailJob, $this->smtpConfig)
            ->delay(now()->addSeconds($delay));
    }
}
