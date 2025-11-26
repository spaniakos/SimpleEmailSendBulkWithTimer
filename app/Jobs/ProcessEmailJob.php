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
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 10;
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
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
        // Refresh models from database
        $this->emailJob->refresh();
        $this->smtpConfig->refresh();

        // Check if job is paused
        if ($this->emailJob->status === 'paused') {
            Log::info("Email job {$this->emailJob->id} is paused. Stopping processing.");
            return;
        }

        // Check if job is already completed or failed
        if (in_array($this->emailJob->status, ['completed', 'failed'])) {
            Log::info("Email job {$this->emailJob->id} is already {$this->emailJob->status}. Stopping processing.");
            return;
        }

        // Update job status to processing if it's pending
        if ($this->emailJob->status === 'pending') {
            $this->emailJob->update(['status' => 'processing']);
        }

        // Get rate limit configuration
        $rateLimitSeconds = config('email.rate_limit.seconds', 10);
        $batchSize = config('email.rate_limit.emails_per_domain', 1);
        $senderDomain = explode('@', $this->smtpConfig->from_email)[1] ?? 'unknown';
        $cacheKey = "email_domain_last_sent_{$senderDomain}";

        // Get pending recipients for this job (batch size from config)
        $pendingRecipients = EmailRecipient::where('email_job_id', $this->emailJob->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($pendingRecipients->isEmpty()) {
            // No more pending recipients, check if job is complete
            $this->updateJobStatus();
            Log::info("Email job {$this->emailJob->id} has no more pending recipients.");
            return;
        }

        $processedCount = 0;
        $now = now()->timestamp;

        foreach ($pendingRecipients as $recipient) {
            // Check rate limit
            $lastSent = Cache::get($cacheKey);
            
            if ($lastSent && ($now - $lastSent) < $rateLimitSeconds) {
                // Rate limit not met, we'll process remaining recipients in next batch
                // Dispatch this job again with a delay
                $delay = $rateLimitSeconds - ($now - $lastSent);
                Log::info("Rate limit reached for domain {$senderDomain}. Dispatching ProcessEmailJob again with {$delay}s delay.");
                
                // Dispatch next batch after rate limit delay
                ProcessEmailJob::dispatch($this->emailJob, $this->smtpConfig)
                    ->delay(now()->addSeconds($delay));
                break;
            }

            try {
                // Send email
                $this->sendEmail($recipient);

                // Update recipient status
                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                // Update job counters
                $this->emailJob->increment('sent_count');

                // Set rate limit cache
                Cache::put($cacheKey, $now, $rateLimitSeconds);

                $processedCount++;
                $now = now()->timestamp; // Update timestamp for next iteration

            } catch (Exception $e) {
                Log::error("Failed to send email to {$recipient->email_address}: " . $e->getMessage());
                
                // Mark recipient as failed
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                // Update job counters
                $this->emailJob->increment('failed_count');
            }
        }

        // Update job status
        $this->updateJobStatus();

        // If there are more pending recipients, dispatch this job again
        $remainingCount = EmailRecipient::where('email_job_id', $this->emailJob->id)
            ->where('status', 'pending')
            ->count();

        if ($remainingCount > 0 && $this->emailJob->status !== 'paused') {
            Log::info("Email job {$this->emailJob->id} has {$remainingCount} remaining recipients. Dispatching next batch.");
            ProcessEmailJob::dispatch($this->emailJob, $this->smtpConfig);
        } else {
            Log::info("Email job {$this->emailJob->id} processing complete. Processed {$processedCount} recipients in this batch.");
        }
    }

    /**
     * Send email to recipient
     */
    protected function sendEmail(EmailRecipient $recipient): void
    {
        // Configure mailer dynamically
        \Illuminate\Support\Facades\Config::set('mail.mailers.smtp', [
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
        Log::info("Sending email to {$recipient->email_address} for job {$this->emailJob->id}");
        
        \Illuminate\Support\Facades\Mail::mailer('smtp')->send([], [], function ($message) use ($recipient) {
            $message->to($recipient->email_address)
                ->subject($this->emailJob->subject)
                ->from($this->smtpConfig->from_email, $this->smtpConfig->from_name)
                ->html($this->emailJob->html_content);
        });

        Log::info("Email successfully sent to {$recipient->email_address}");
    }

    /**
     * Update job status based on completion
     */
    protected function updateJobStatus(): void
    {
        $this->emailJob->refresh();
        
        $totalProcessed = $this->emailJob->sent_count + $this->emailJob->failed_count;
        
        if ($totalProcessed >= $this->emailJob->total_recipients) {
            if ($this->emailJob->failed_count > 0 && $this->emailJob->sent_count > 0) {
                $this->emailJob->update(['status' => 'completed']);
            } elseif ($this->emailJob->failed_count === $this->emailJob->total_recipients) {
                $this->emailJob->update(['status' => 'failed']);
            } else {
                $this->emailJob->update(['status' => 'completed']);
            }
        }
    }
}

