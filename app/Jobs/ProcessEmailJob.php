<?php

namespace App\Jobs;

use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
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
        $rateLimit = config('email.rate_limit.seconds', 10);
        $batchSize = config('email.rate_limit.emails_per_domain', 1);

        // 1️⃣ Prevent Parallel Execution
        if (Cache::has($lockKey)) {
            $this->scheduleNextRun($rateLimit);

            return;
        }

        Cache::put($lockKey, true, 60);

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
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($pending->isEmpty()) {
                $this->finishJobIfComplete();
                Cache::forget($lockKey);

                return;
            }

            foreach ($pending as $recipient) {
                if (! $this->canSendNow($rateLimit)) {
                    Cache::forget($lockKey);
                    // Calculate remaining time until rate limit expires
                    $domain = explode('@', $this->smtpConfig->from_email)[1] ?? 'unknown';
                    $cacheKey = "email_domain_last_sent_{$domain}";
                    $lastSent = Cache::get($cacheKey);
                    if ($lastSent) {
                        $timeSinceLastSent = now()->timestamp - $lastSent;
                        $remainingDelay = max(0, $rateLimit - $timeSinceLastSent);
                        $this->scheduleNextRun($remainingDelay);
                    } else {
                        $this->scheduleNextRun($rateLimit);
                    }

                    return;
                }

                $this->sendToRecipient($recipient);
            }

            $this->finishJobIfComplete();

            // 4️⃣ Continue if more exist
            if ($this->hasPendingRecipients() && $this->emailJob->status !== 'paused') {
                Cache::forget($lockKey);
                // Calculate delay based on when last email was sent
                $domain = explode('@', $this->smtpConfig->from_email)[1] ?? 'unknown';
                $cacheKey = "email_domain_last_sent_{$domain}";
                $lastSent = Cache::get($cacheKey);
                if ($lastSent) {
                    $timeSinceLastSent = now()->timestamp - $lastSent;
                    $remainingDelay = max(0, $rateLimit - $timeSinceLastSent);
                    $this->scheduleNextRun($remainingDelay);
                } else {
                    $this->scheduleNextRun($rateLimit);
                }

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

    private function canSendNow(int $rateLimit): bool
    {
        $domain = explode('@', $this->smtpConfig->from_email)[1] ?? 'unknown';
        $cacheKey = "email_domain_last_sent_{$domain}";

        $last = Cache::get($cacheKey);
        if (! $last) {
            return true;
        }

        return (now()->timestamp - $last) >= $rateLimit;
    }

    private function sendToRecipient($recipient): void
    {
        try {
            $this->configureMailer();
            Log::info("Sending email to {$recipient->email_address}");

            Mail::mailer('smtp')->send([], [], function ($message) use ($recipient) {
                $message->to($recipient->email_address)
                    ->subject($this->emailJob->subject)
                    ->from($this->smtpConfig->from_email, $this->smtpConfig->from_name)
                    ->html($this->emailJob->html_content);
            });

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
            $this->emailJob->increment('sent_count');

            $domain = explode('@', $this->smtpConfig->from_email)[1] ?? 'unknown';
            Cache::put("email_domain_last_sent_{$domain}", now()->timestamp, 3600);

        } catch (Exception $e) {
            Log::error("Failed email to {$recipient->email_address}: ".$e->getMessage());
            $recipient->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            $this->emailJob->increment('failed_count');
        }
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
        $total = $this->emailJob->sent_count + $this->emailJob->failed_count;

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
