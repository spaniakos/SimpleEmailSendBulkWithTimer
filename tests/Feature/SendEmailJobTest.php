<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_email_job_can_be_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
            'email_address' => 'test@example.com',
            'domain' => 'example.com',
        ]);

        SendEmailJob::dispatch($recipient, $job, $smtp);

        Queue::assertPushed(SendEmailJob::class, function ($queuedJob) use ($recipient) {
            return $queuedJob->recipient->id === $recipient->id;
        });
    }

    public function test_rate_limiting_prevents_multiple_emails_per_domain(): void
    {
        Mail::fake();
        Cache::flush();

        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
            'email_address' => 'test1@example.com',
            'domain' => 'example.com',
        ]);

        // First email should be sent
        $sendJob = new SendEmailJob($recipient, $job, $smtp);
        $sendJob->handle();

        // Check that cache was set
        $this->assertTrue(Cache::has('email_domain_last_sent_example.com'));
        
        // Verify recipient was updated to sent
        $recipient->refresh();
        $this->assertEquals('sent', $recipient->status);
        $this->assertNotNull($recipient->sent_at);

        // Second email to same domain should be delayed (released, not sent)
        $recipient2 = EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
            'email_address' => 'test2@example.com',
            'domain' => 'example.com',
            'status' => 'pending',
            'sent_at' => null,
        ]);

        $sendJob2 = new SendEmailJob($recipient2, $job, $smtp);
        
        // Should release the job due to rate limiting (this will return early)
        // Note: release() may throw an exception in tests, so we catch it
        try {
            $sendJob2->handle();
        } catch (\Illuminate\Queue\ManuallyFailedException $e) {
            // Job was released, which is expected
        } catch (\Exception $e) {
            // Other exceptions might occur, but job should still be released
        }

        // Verify second recipient is still pending (not updated because job was released)
        $recipient2->refresh();
        // The recipient should remain pending since the job was released before processing
        $this->assertEquals('pending', $recipient2->status);
        // sent_at should remain null or unchanged (factory might set a default, so we check it wasn't updated to a recent time)
        if ($recipient2->sent_at) {
            // If sent_at exists, it should be from factory, not from job execution
            $this->assertLessThan(now()->subSecond(), $recipient2->sent_at);
        }
        
        // Verify cache still exists (rate limit is still active)
        $this->assertTrue(Cache::has('email_domain_last_sent_example.com'));
    }

    public function test_email_job_updates_recipient_status_on_success(): void
    {
        Mail::fake();
        Cache::flush();

        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
            'email_address' => 'test@example.com',
            'domain' => 'example.com',
            'status' => 'pending',
        ]);

        $sendJob = new SendEmailJob($recipient, $job, $smtp);
        $sendJob->handle();

        $recipient->refresh();
        $this->assertEquals('sent', $recipient->status);
        $this->assertNotNull($recipient->sent_at);
    }

    public function test_email_job_updates_counters_on_success(): void
    {
        Mail::fake();
        Cache::flush();

        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
            'sent_count' => 0,
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
            'email_address' => 'test@example.com',
            'domain' => 'example.com',
        ]);

        $sendJob = new SendEmailJob($recipient, $job, $smtp);
        $sendJob->handle();

        $job->refresh();
        $this->assertEquals(1, $job->sent_count);
    }
}
