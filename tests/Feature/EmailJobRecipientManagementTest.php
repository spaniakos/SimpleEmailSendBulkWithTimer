<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\MailingList;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailJobRecipientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_page_offers_pending_removal_and_only_active_contacts_from_the_selected_list(): void
    {
        [$owner, $list, $job, $included, $available, $unsubscribed] = $this->createJobWithContacts('paused');
        $this->createRecipient($job, $included);
        $unsubscribed->update(['unsubscribed_at' => now()]);

        $response = $this->actingAs($owner)->get("/jobs/{$job->id}");

        $response->assertOk();
        $response->assertSee('Add recipient');
        $response->assertSee($available->email_address);
        $response->assertSee('Remove from job');
        $response->assertDontSee($unsubscribed->email_address);
    }

    public function test_owner_can_add_an_active_contact_from_the_selected_list_to_a_paused_job(): void
    {
        [$owner, $list, $job, $included, $available] = $this->createJobWithContacts('paused');
        $this->createRecipient($job, $included);

        $response = $this->actingAs($owner)->post("/jobs/{$job->id}/recipients", [
            'contact_id' => $available->id,
        ]);

        $response->assertRedirect("/jobs/{$job->id}");
        $this->assertDatabaseHas('email_recipients', [
            'email_job_id' => $job->id,
            'contact_id' => $available->id,
            'email_address' => $available->email_address,
            'domain' => 'example.net',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'total_recipients' => 2]);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'status' => 'paused']);
    }

    public function test_recipient_already_active_in_the_job_cannot_be_added_twice(): void
    {
        [$owner, $list, $job, $included] = $this->createJobWithContacts('paused');
        $this->createRecipient($job, $included);

        $this->actingAs($owner)->post("/jobs/{$job->id}/recipients", [
            'contact_id' => $included->id,
        ])->assertSessionHasErrors('contact_id');

        $this->assertDatabaseCount('email_recipients', 1);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'total_recipients' => 1]);
    }

    public function test_owner_can_remove_a_pending_recipient_without_deleting_its_audit_record(): void
    {
        [$owner, $list, $job, $included] = $this->createJobWithContacts('pending');
        $recipient = $this->createRecipient($job, $included);

        $response = $this->actingAs($owner)->delete("/jobs/{$job->id}/recipients/{$recipient->id}");

        $response->assertRedirect("/jobs/{$job->id}");
        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'email_job_id' => $job->id,
            'status' => 'removed',
        ]);
        $this->assertNotNull($recipient->fresh()->removed_at);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'total_recipients' => 0]);
    }

    public function test_recipient_addition_rejects_contacts_outside_the_selected_list_and_unsubscribed_contacts(): void
    {
        [$owner, $list, $job, $included, $available, $unsubscribed] = $this->createJobWithContacts('paused');
        $outsideList = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'outside@example.org',
        ]);

        $this->actingAs($owner)->post("/jobs/{$job->id}/recipients", [
            'contact_id' => $outsideList->id,
        ])->assertSessionHasErrors('contact_id');
        $this->actingAs($owner)->post("/jobs/{$job->id}/recipients", [
            'contact_id' => $unsubscribed->id,
        ])->assertSessionHasErrors('contact_id');

        $this->assertDatabaseCount('email_recipients', 0);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'total_recipients' => 1]);
    }

    public function test_sent_recipient_cannot_be_removed_from_job_history(): void
    {
        [$owner, $list, $job, $included] = $this->createJobWithContacts('paused');
        $recipient = $this->createRecipient($job, $included, 'sent');
        $job->update(['total_recipients' => 1, 'sent_count' => 1]);

        $this->actingAs($owner)->delete("/jobs/{$job->id}/recipients/{$recipient->id}")
            ->assertSessionHasErrors('recipient');

        $this->assertDatabaseHas('email_recipients', ['id' => $recipient->id, 'status' => 'sent']);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'total_recipients' => 1, 'sent_count' => 1]);
    }

    public function test_recipients_cannot_be_changed_while_a_job_is_processing_or_locked_by_the_worker(): void
    {
        [$owner, $list, $job, $included, $available] = $this->createJobWithContacts('processing');
        $this->createRecipient($job, $included);

        $this->actingAs($owner)->post("/jobs/{$job->id}/recipients", [
            'contact_id' => $available->id,
        ])->assertStatus(409);

        $job->update(['status' => 'paused']);
        Cache::put("email_job_processing_{$job->id}", true, 60);

        $this->actingAs($owner)->post("/jobs/{$job->id}/recipients", [
            'contact_id' => $available->id,
        ])->assertStatus(409);

        $this->assertDatabaseCount('email_recipients', 1);
        $this->assertDatabaseHas('email_jobs', ['id' => $job->id, 'total_recipients' => 1]);
    }

    public function test_owner_cannot_manage_a_recipient_from_another_job_or_another_owners_job(): void
    {
        [$owner, $list, $job, $included] = $this->createJobWithContacts('paused');
        $recipient = $this->createRecipient($job, $included);
        $otherOwner = User::factory()->create();
        $otherSmtp = SmtpConfiguration::factory()->create(['user_id' => $otherOwner->id]);
        $otherJob = EmailJob::factory()->create([
            'user_id' => $otherOwner->id,
            'smtp_configuration_id' => $otherSmtp->id,
            'mailing_list_id' => MailingList::create(['user_id' => $otherOwner->id, 'name' => 'Other list'])->id,
            'status' => 'paused',
            'total_recipients' => 0,
        ]);
        $sameOwnerOtherJob = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $job->smtp_configuration_id,
            'mailing_list_id' => MailingList::create(['user_id' => $owner->id, 'name' => 'Another list'])->id,
            'status' => 'paused',
            'total_recipients' => 0,
        ]);

        $this->actingAs($owner)->delete("/jobs/{$sameOwnerOtherJob->id}/recipients/{$recipient->id}")
            ->assertNotFound();
        $this->actingAs($owner)->delete("/jobs/{$otherJob->id}/recipients/{$recipient->id}")
            ->assertForbidden();
        $this->actingAs($otherOwner)->delete("/jobs/{$job->id}/recipients/{$recipient->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('email_recipients', ['id' => $recipient->id, 'status' => 'pending']);
    }

    private function createJobWithContacts(string $status): array
    {
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $list = MailingList::create(['user_id' => $owner->id, 'name' => 'Selected list']);
        $included = Contact::create(['user_id' => $owner->id, 'email_address' => 'included@example.com']);
        $available = Contact::create(['user_id' => $owner->id, 'email_address' => 'available@example.net']);
        $unsubscribed = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'unsubscribed@example.org',
            'unsubscribed_at' => now(),
        ]);
        $list->contacts()->attach([$included->id, $available->id, $unsubscribed->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => $status,
            'total_recipients' => 1,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        return [$owner, $list, $job, $included, $available, $unsubscribed];
    }

    private function createRecipient(EmailJob $job, Contact $contact, string $status = 'pending'): EmailRecipient
    {
        return EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => substr(strrchr($contact->email_address, '@'), 1),
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
