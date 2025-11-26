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
use Illuminate\Support\Facades\Log;
use Exception;

class SendEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 10;
    public $maxExceptions = 3;
    
    // Track release attempts to prevent infinite loops
    private $releaseAttempts = 0;
    private const MAX_RELEASE_ATTEMPTS = 100; // Maximum times a job can be released

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
        
        // Check rate limit per sender domain (from SMTP configuration)
        $senderDomain = explode('@', $this->smtpConfig->from_email)[1] ?? 'unknown';
        $cacheKey = "email_domain_last_sent_{$senderDomain}";
        
        $lastSent = Cache::get($cacheKey);
        $now = now()->timestamp;
        
        // Track release attempts using cache to persist across job instances
        $releaseAttemptsKey = "email_job_release_attempts_{$this->recipient->id}";
        $this->releaseAttempts = Cache::get($releaseAttemptsKey, 0);
        
        if ($lastSent && ($now - $lastSent) < $rateLimitSeconds) {
            // Check if we've exceeded max release attempts
            if ($this->releaseAttempts >= self::MAX_RELEASE_ATTEMPTS) {
                Log::warning("Email job for recipient {$this->recipient->email_address} exceeded max release attempts. Marking as failed.");
                
                // Mark recipient as failed due to too many rate limit releases
                $this->recipient->update([
                    'status' => 'failed',
                    'error_message' => 'Job exceeded maximum release attempts due to rate limiting. Please check queue worker is running.',
                ]);
                
                $this->emailJob->increment('failed_count');
                $this->updateJobStatus();
                
                // Clean up release attempts cache
                Cache::forget($releaseAttemptsKey);
                
                // Fail the job
                $this->fail(new Exception('Exceeded maximum release attempts due to rate limiting'));
                return;
            }
            
            // Rate limit not met, release job to retry later
            $delay = $rateLimitSeconds - ($now - $lastSent);
            
            // Increment release attempts
            $this->releaseAttempts++;
            Cache::put($releaseAttemptsKey, $this->releaseAttempts, now()->addHours(24));
            
            Log::info("Email job for recipient {$this->recipient->email_address} rate limited. Releasing with {$delay}s delay. Attempt: {$this->releaseAttempts}/" . self::MAX_RELEASE_ATTEMPTS);
            
            $this->release($delay);
            return;
        }
        
        // Rate limit passed, clear release attempts counter
        Cache::forget($releaseAttemptsKey);

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

            // Send email using the SMTP mailer explicitly
            Log::info("Sending email to {$this->recipient->email_address} for job {$this->emailJob->id}");
            
            Mail::mailer('smtp')->send([], [], function ($message) {
                $message->to($this->recipient->email_address)
                    ->subject($this->emailJob->subject)
                    ->from($this->smtpConfig->from_email, $this->smtpConfig->from_name)
                    ->html($this->emailJob->html_content);
            });

            Log::info("Email successfully sent to {$this->recipient->email_address}");

            // Update recipient status
            $this->recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            
            // Clear any release attempts cache since email was sent successfully
            $releaseAttemptsKey = "email_job_release_attempts_{$this->recipient->id}";
            Cache::forget($releaseAttemptsKey);

            // Update job counters
            $this->emailJob->increment('sent_count');

            // Set rate limit cache
            $rateLimitSeconds = config('email.rate_limit.seconds', 10);
            Cache::put($cacheKey, $now, $rateLimitSeconds);

            // Update job status if all emails are sent
            $this->updateJobStatus();

        } catch (Exception $e) {
            Log::error("Failed to send email to {$this->recipient->email_address}: " . $e->getMessage());
            
            // Mark recipient as failed
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Update job counters
            $this->emailJob->increment('failed_count');

            // Update job status
            $this->updateJobStatus();
            
            // Clear release attempts cache
            $releaseAttemptsKey = "email_job_release_attempts_{$this->recipient->id}";
            Cache::forget($releaseAttemptsKey);

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
