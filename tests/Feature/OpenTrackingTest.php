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

class OpenTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_opted_in_campaign_embeds_a_recipient_scoped_token_stored_as_a_hash_and_records_first_open(): void
    {
        Mail::fake();
        Cache::flush();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'subscriber@example.com',
        ]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'News']);
        $list->contacts()->attach($contact->id);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'processing',
            'total_recipients' => 1,
            'html_content' => '<p>Campaign message</p>',
            'track_opens' => true,
        ]);
        $recipient = EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.com',
            'status' => 'pending',
        ]);

        (new ProcessEmailJob($job, $smtp))->handle();

        $message = null;
        Mail::assertSent('App\\Mail\\CampaignEmail', function ($sent) use (&$message): bool {
            $message = $sent;

            return true;
        });

        preg_match('#/tracking/open/([a-f0-9]{64})#', $message->render(), $matches);
        $token = $matches[1] ?? null;
        $this->assertNotNull($token);
        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'open_tracking_token_hash' => hash('sha256', $token),
            'opened_at' => null,
        ]);
        $this->assertDatabaseMissing('email_recipients', [
            'id' => $recipient->id,
            'open_tracking_token_hash' => $token,
        ]);

        $firstOpen = now();
        $this->travelTo($firstOpen);
        $response = $this->get(route('tracking.open', ['token' => $token]));
        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'opened_at' => $firstOpen->toDateTimeString(),
        ]);

        $this->travel(1)->minutes();
        $this->get(route('tracking.open', ['token' => $token]))->assertOk();
        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'opened_at' => $firstOpen->toDateTimeString(),
        ]);
    }

    public function test_open_tracking_is_omitted_when_campaign_tracking_is_disabled(): void
    {
        Mail::fake();
        Cache::flush();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'subscriber@example.com',
        ]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'News']);
        $list->contacts()->attach($contact->id);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'processing',
            'total_recipients' => 1,
            'html_content' => '<p>Campaign message</p>',
            'track_opens' => false,
        ]);
        $recipient = EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.com',
            'status' => 'pending',
        ]);

        (new ProcessEmailJob($job, $smtp))->handle();

        Mail::assertSent('App\\Mail\\CampaignEmail', fn ($message): bool => ! str_contains($message->render(), '/tracking/open/'));
        $this->assertNull($recipient->fresh()->open_tracking_token_hash);
    }

    public function test_invalid_tracking_tokens_return_an_indistinguishable_pixel_without_changing_data(): void
    {
        $response = $this->get(route('tracking.open', ['token' => str_repeat('a', 64)]));

        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('email_recipients', 0);
    }

    public function test_message_details_show_tracking_state_for_each_recipient_and_the_aggregate_count(): void
    {
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'opened@example.com',
        ]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'News']);
        $list->contacts()->attach($contact->id);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'completed',
            'total_recipients' => 2,
            'sent_count' => 2,
            'html_content' => '<p>Campaign message</p>',
            'track_opens' => true,
        ]);
        EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.com',
            'status' => 'sent',
            'sent_at' => now(),
            'opened_at' => now(),
        ]);
        EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => 'not-opened@example.com',
            'domain' => 'example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Opened')
            ->assertSee('Not opened')
            ->assertSee('approximate unique recipients');
    }
}
