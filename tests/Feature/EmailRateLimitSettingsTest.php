<?php

namespace Tests\Feature;

use App\Jobs\ProcessEmailJob;
use App\Models\Contact;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\MailingList;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailRateLimitSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_exposes_global_and_recipient_domain_rolling_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.email.edit'))
            ->assertOk()
            ->assertSee('Maximum emails per window')
            ->assertSee('Global window (seconds)')
            ->assertSee('Maximum emails per recipient domain')
            ->assertSee('Recipient domain window (seconds)');
    }

    public function test_owner_can_save_valid_rate_limits_and_workers_read_them_without_restart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.email.update'), [
            'unsubscribe_footer_text' => 'Unsubscribe',
            'max_emails_per_window' => 25,
            'window_seconds' => 300,
            'max_emails_per_domain' => 3,
            'domain_window_seconds' => 120,
        ])->assertRedirect(route('settings.email.edit'));

        $this->assertDatabaseHas('email_settings', [
            'user_id' => $user->id,
            'max_emails_per_window' => 25,
            'window_seconds' => 300,
            'max_emails_per_domain' => 3,
            'domain_window_seconds' => 120,
        ]);
    }

    public function test_invalid_rate_limits_are_rejected_without_changing_existing_settings(): void
    {
        $user = User::factory()->create();
        $user->emailSettings()->create([
            'unsubscribe_footer_text' => 'Footer',
            'max_emails_per_window' => 10,
            'window_seconds' => 60,
            'max_emails_per_domain' => 2,
            'domain_window_seconds' => 60,
        ]);

        $this->actingAs($user)->from(route('settings.email.edit'))->put(route('settings.email.update'), [
            'unsubscribe_footer_text' => 'Changed',
            'max_emails_per_window' => 0,
            'window_seconds' => 0,
            'max_emails_per_domain' => -1,
            'domain_window_seconds' => 0,
        ])->assertRedirect(route('settings.email.edit'))->assertSessionHasErrors([
            'max_emails_per_window', 'window_seconds', 'max_emails_per_domain', 'domain_window_seconds',
        ]);

        $this->assertDatabaseHas('email_settings', [
            'user_id' => $user->id,
            'unsubscribe_footer_text' => 'Footer',
            'max_emails_per_window' => 10,
            'window_seconds' => 60,
            'max_emails_per_domain' => 2,
            'domain_window_seconds' => 60,
        ]);
    }

    public function test_campaign_skips_a_capped_recipient_domain_and_sends_another_domain_within_global_cap(): void
    {
        Mail::fake();
        Cache::flush();

        $owner = User::factory()->create();
        $owner->emailSettings()->create([
            'unsubscribe_footer_text' => 'Footer',
            'max_emails_per_window' => 2,
            'window_seconds' => 60,
            'max_emails_per_domain' => 1,
            'domain_window_seconds' => 60,
        ]);
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'Selected']);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'processing',
            'total_recipients' => 3,
        ]);

        foreach (['one@gmail.com', 'two@gmail.com', 'three@hotmail.com'] as $address) {
            $contact = Contact::create(['user_id' => $owner->id, 'email_address' => $address]);
            $list->contacts()->attach($contact->id);
            EmailRecipient::create([
                'email_job_id' => $job->id,
                'contact_id' => $contact->id,
                'email_address' => $address,
                'domain' => substr(strrchr($address, '@'), 1),
                'status' => 'pending',
            ]);
        }

        (new ProcessEmailJob($job, $smtp))->handle();

        $this->assertDatabaseHas('email_recipients', ['email_address' => 'one@gmail.com', 'status' => 'sent']);
        $this->assertDatabaseHas('email_recipients', ['email_address' => 'two@gmail.com', 'status' => 'pending']);
        $this->assertDatabaseHas('email_recipients', ['email_address' => 'three@hotmail.com', 'status' => 'sent']);
        Mail::assertSentCount(2);
    }

    public function test_workers_enforce_the_global_window_cap_across_campaign_jobs(): void
    {
        Mail::fake();
        Cache::flush();

        $owner = User::factory()->create();
        $owner->emailSettings()->create([
            'unsubscribe_footer_text' => 'Footer',
            'max_emails_per_window' => 1,
            'window_seconds' => 300,
            'max_emails_per_domain' => 10,
            'domain_window_seconds' => 300,
        ]);
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'Selected']);
        $jobs = collect(['first@example.com', 'second@other.test'])->map(function (string $address) use ($owner, $smtp, $list) {
            $contact = Contact::create(['user_id' => $owner->id, 'email_address' => $address]);
            $list->contacts()->attach($contact->id);
            $job = EmailJob::factory()->create([
                'user_id' => $owner->id,
                'smtp_configuration_id' => $smtp->id,
                'mailing_list_id' => $list->id,
                'status' => 'processing',
                'total_recipients' => 1,
            ]);
            $recipient = EmailRecipient::create([
                'email_job_id' => $job->id,
                'contact_id' => $contact->id,
                'email_address' => $address,
                'domain' => substr(strrchr($address, '@'), 1),
                'status' => 'pending',
            ]);

            return [$job, $recipient];
        });

        foreach ($jobs as [$job, $recipient]) {
            (new ProcessEmailJob($job, $smtp))->handle();
        }

        $this->assertSame(1, EmailRecipient::where('status', 'sent')->count());
        $this->assertSame(1, EmailRecipient::where('status', 'pending')->count());
        Mail::assertSentCount(1);
    }

    public function test_queued_jobs_use_rate_settings_changed_without_restarting_workers(): void
    {
        Mail::fake();
        Cache::flush();

        $owner = User::factory()->create();
        $settings = $owner->emailSettings()->create([
            'unsubscribe_footer_text' => 'Footer',
            'max_emails_per_window' => 1,
            'window_seconds' => 300,
            'max_emails_per_domain' => 10,
            'domain_window_seconds' => 300,
        ]);
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'Selected']);
        $jobs = collect(['first@gmail.com', 'second@hotmail.com'])->map(function (string $address) use ($owner, $smtp, $list) {
            $contact = Contact::create(['user_id' => $owner->id, 'email_address' => $address]);
            $list->contacts()->attach($contact->id);
            $job = EmailJob::factory()->create([
                'user_id' => $owner->id,
                'smtp_configuration_id' => $smtp->id,
                'mailing_list_id' => $list->id,
                'status' => 'processing',
                'total_recipients' => 1,
            ]);
            $recipient = EmailRecipient::create([
                'email_job_id' => $job->id,
                'contact_id' => $contact->id,
                'email_address' => $address,
                'domain' => substr(strrchr($address, '@'), 1),
                'status' => 'pending',
            ]);

            return [$job, $recipient];
        });

        [$firstJob] = $jobs[0];
        [$secondJob, $secondRecipient] = $jobs[1];
        (new ProcessEmailJob($firstJob, $smtp))->handle();
        (new ProcessEmailJob($secondJob, $smtp))->handle();

        $this->assertSame('pending', $secondRecipient->fresh()->status);
        $settings->update(['max_emails_per_window' => 2]);
        (new ProcessEmailJob($secondJob, $smtp))->handle();

        $this->assertSame('sent', $secondRecipient->fresh()->status);
        Mail::assertSentCount(2);
    }
}
