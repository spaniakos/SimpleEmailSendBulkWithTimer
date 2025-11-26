<?php

namespace App\Jobs;

use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Exception;

class SendEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailRecipient $recipient,
        public EmailJob $emailJob,
        public SmtpConfiguration $smtpConfig
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh models from database to ensure we have latest data
        $this->recipient->refresh();
        $this->emailJob->refresh();
        $this->smtpConfig->refresh();
        
        // Get rate limit configuration from config
        $rateLimitSeconds = config('email.rate_limit.seconds', 10);
        $rateLimitCount = config('email.rate_limit.emails_per_domain', 1);
        
        // Check rate limit per domain
        $domain = $this->recipient->domain;
        $cacheKey = "email_domain_last_sent_{$domain}";
        
        $lastSent = Cache::get($cacheKey);
        $now = now()->timestamp;
        
        if ($lastSent && ($now - $lastSent) < $rateLimitSeconds) {
            // Rate limit not met, release job to retry later
            $delay = $rateLimitSeconds - ($now - $lastSent);
            $this->release($delay);
            return;
        }

        try {
            // Configure mailer dynamically
            Config::set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'host' => $this->smtpConfig->host,
                'port' => $this->smtpConfig->port,
                'encryption' => $this->smtpConfig->encryption,
                'username' => $this->smtpConfig->username,
                'password' => $this->smtpConfig->password,
                'timeout' => null,
                'local_domain' => null,
            ]);

            // Send email
            Mail::send([], [], function ($message) {
                $message->to($this->recipient->email_address)
                    ->subject($this->emailJob->subject)
                    ->from($this->smtpConfig->from_email, $this->smtpConfig->from_name)
                    ->html($this->emailJob->html_content);
            });

            // Update recipient status
            $this->recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Update job counters
            $this->emailJob->increment('sent_count');

            // Set rate limit cache
            $rateLimitSeconds = config('email.rate_limit.seconds', 10);
            Cache::put($cacheKey, $now, $rateLimitSeconds);

            // Update job status if all emails are sent
            $this->updateJobStatus();

        } catch (Exception $e) {
            // Mark recipient as failed
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Update job counters
            $this->emailJob->increment('failed_count');

            // Update job status
            $this->updateJobStatus();

            throw $e;
        }
    }

    protected function updateJobStatus(): void
    {
        $totalSent = $this->emailJob->sent_count + $this->emailJob->failed_count;
        
        if ($totalSent >= $this->emailJob->total_recipients) {
            if ($this->emailJob->failed_count > 0 && $this->emailJob->sent_count > 0) {
                $this->emailJob->update(['status' => 'completed']);
            } elseif ($this->emailJob->failed_count === $this->emailJob->total_recipients) {
                $this->emailJob->update(['status' => 'failed']);
            } else {
                $this->emailJob->update(['status' => 'completed']);
            }
        } elseif ($this->emailJob->status === 'pending') {
            $this->emailJob->update(['status' => 'processing']);
        }
    }
}
