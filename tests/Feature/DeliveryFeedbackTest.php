<?php

namespace Tests\Feature;

use App\Jobs\ProcessEmailJob;
use App\Models\Contact;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\MailingList;
use App\Models\SmtpConfiguration;
use App\Models\User;
use App\Services\EmailDeliveryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_recipient_has_a_unique_message_id_for_delivery_report_correlation(): void
    {
        $recipient = $this->sentRecipient();

        $this->assertNotEmpty($recipient->fresh()->message_id);
        $this->assertMatchesRegularExpression('/^<[^<>\s@]+@[^<>\s@]+>$/', $recipient->fresh()->message_id);

        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'delivery_status' => 'accepted',
        ]);
    }

    public function test_send_stores_a_distinct_message_id_and_sets_the_same_outbound_header(): void
    {
        Mail::fake();
        Cache::flush();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::create(['user_id' => $owner->id, 'email_address' => 'one@example.test']);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'Send list']);
        $list->contacts()->attach($contact->id);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'processing',
            'total_recipients' => 1,
            'html_content' => '<p>Campaign message</p>',
        ]);
        $recipient = EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.test',
            'status' => 'pending',
        ]);

        (new ProcessEmailJob($job, $smtp))->handle();

        $storedMessageId = $recipient->fresh()->message_id;
        $this->assertNotEmpty($storedMessageId);
        $this->assertMatchesRegularExpression('/^<[^<>\s@]+@[^<>\s@]+>$/', $storedMessageId);
        Mail::assertSent('App\\Mail\\CampaignEmail', function ($message) use ($storedMessageId): bool {
            return $message->headers()->messageId === trim($storedMessageId, '<>');
        });
    }

    public function test_hard_bounce_is_recorded_and_suppresses_the_contact_across_future_lists(): void
    {
        $recipient = $this->sentRecipient();

        $recorded = app(EmailDeliveryReportService::class)->record(
            messageId: $recipient->message_id,
            type: 'hard_bounce',
            diagnostic: '550 5.1.1 Recipient address rejected',
            occurredAt: now(),
            reportId: 'report-hard-1',
        );

        $this->assertTrue($recorded);
        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'delivery_status' => 'bounced',
        ]);
        $this->assertDatabaseHas('contacts', [
            'id' => $recipient->contact_id,
            'delivery_suppression_reason' => 'hard_bounce',
        ]);
        $this->assertDatabaseHas('email_delivery_events', [
            'email_recipient_id' => $recipient->id,
            'type' => 'hard_bounce',
            'report_id' => 'report-hard-1',
        ]);

        $otherList = MailingList::create(['user_id' => $recipient->emailJob->user_id, 'name' => 'Later campaign']);
        $this->assertFalse($otherList->contacts()->whereKey($recipient->contact_id)->exists());
    }

    public function test_soft_bounce_is_recorded_without_global_suppression_or_retry(): void
    {
        $recipient = $this->sentRecipient();

        app(EmailDeliveryReportService::class)->record(
            messageId: $recipient->message_id,
            type: 'soft_bounce',
            diagnostic: '451 4.2.2 Mailbox temporarily full',
            occurredAt: now(),
            reportId: 'report-soft-1',
        );

        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
            'delivery_status' => 'deferred',
        ]);
        $this->assertDatabaseHas('contacts', [
            'id' => $recipient->contact_id,
            'delivery_suppression_reason' => null,
        ]);
    }

    public function test_complaint_is_recorded_and_globally_suppresses_future_sends(): void
    {
        $recipient = $this->sentRecipient();

        app(EmailDeliveryReportService::class)->record(
            messageId: $recipient->message_id,
            type: 'complaint',
            diagnostic: 'Feedback-Type: abuse',
            occurredAt: now(),
            reportId: 'report-complaint-1',
        );

        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'delivery_status' => 'complained',
        ]);
        $this->assertDatabaseHas('contacts', [
            'id' => $recipient->contact_id,
            'delivery_suppression_reason' => 'complaint',
        ]);
    }

    public function test_global_delivery_suppression_blocks_a_recipient_already_queued_in_a_later_campaign(): void
    {
        Mail::fake();
        Cache::flush();
        $firstRecipient = $this->sentRecipient();
        app(EmailDeliveryReportService::class)->record(
            messageId: $firstRecipient->message_id,
            type: 'hard_bounce',
            diagnostic: '550 5.1.1 Recipient address rejected',
            occurredAt: now(),
            reportId: 'global-suppression-test',
        );

        $owner = User::findOrFail($firstRecipient->emailJob->user_id);
        $smtp = $firstRecipient->emailJob->smtpConfiguration;
        $laterList = MailingList::create(['user_id' => $owner->id, 'name' => 'Later list']);
        $laterList->contacts()->attach($firstRecipient->contact_id);
        $laterJob = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $laterList->id,
            'status' => 'processing',
            'total_recipients' => 1,
            'html_content' => '<p>Later campaign</p>',
        ]);
        $laterRecipient = EmailRecipient::create([
            'email_job_id' => $laterJob->id,
            'contact_id' => $firstRecipient->contact_id,
            'email_address' => $firstRecipient->email_address,
            'domain' => $firstRecipient->domain,
            'status' => 'pending',
        ]);

        (new ProcessEmailJob($laterJob, $smtp))->handle();

        $this->assertSame('suppressed', $laterRecipient->fresh()->status);
        $this->assertSame(0, $laterJob->fresh()->sent_count);
        $this->assertSame(1, $laterJob->fresh()->suppressed_count);
        Mail::assertNothingOutgoing();
    }

    public function test_unmatched_and_duplicate_reports_are_safe_idempotent_no_ops(): void
    {
        $recipient = $this->sentRecipient();
        $service = app(EmailDeliveryReportService::class);

        $this->assertFalse($service->record(
            messageId: '<unknown@example.test>',
            type: 'hard_bounce',
            diagnostic: '550 rejected',
            occurredAt: now(),
            reportId: 'unknown-report',
        ));

        $this->assertTrue($service->record(
            messageId: $recipient->message_id,
            type: 'hard_bounce',
            diagnostic: '550 5.1.1 Recipient address rejected',
            occurredAt: now(),
            reportId: 'report-hard-duplicate',
        ));
        $this->assertFalse($service->record(
            messageId: $recipient->message_id,
            type: 'hard_bounce',
            diagnostic: '550 5.1.1 Recipient address rejected',
            occurredAt: now(),
            reportId: 'report-hard-duplicate',
        ));

        $this->assertDatabaseCount('email_delivery_events', 1);
    }

    public function test_report_diagnostics_are_bounded_and_rendered_as_text_in_message_details(): void
    {
        $recipient = $this->sentRecipient();
        $diagnostic = '<script>alert("no")</script>'.str_repeat('x', 5000);

        app(EmailDeliveryReportService::class)->record(
            messageId: $recipient->message_id,
            type: 'hard_bounce',
            diagnostic: $diagnostic,
            occurredAt: now(),
            reportId: 'report-safe-1',
        );

        $owner = User::findOrFail($recipient->emailJob->user_id);
        $response = $this->actingAs($owner)->get(route('jobs.show', $recipient->email_job_id));

        $response->assertOk()
            ->assertSee('Hard bounce')
            ->assertSee('&lt;script&gt;alert(&quot;no&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("no")</script>', false);

        $this->assertDatabaseHas('email_delivery_events', [
            'email_recipient_id' => $recipient->id,
            'diagnostic' => mb_substr($diagnostic, 0, 1000),
        ]);
    }

    private function sentRecipient(): EmailRecipient
    {
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'subscriber@example.test',
        ]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'News']);
        $list->contacts()->attach($contact->id);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'completed',
            'total_recipients' => 1,
            'sent_count' => 1,
            'html_content' => '<p>Campaign message</p>',
        ]);

        return EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.test',
            'status' => 'sent',
            'sent_at' => now(),
            'delivery_status' => 'accepted',
            'message_id' => '<random-message-id@example.test>',
        ]);
    }
}
