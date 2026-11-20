<?php

namespace Tests\Feature;

use App\Jobs\ProcessEmailJob;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_named_list_and_import_unique_normalized_addresses(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post('/lists', [
            'name' => 'Workshop attendees',
            'csv_text' => " Alice@Example.com, bob@example.com\nALICE@example.com ",
        ]);

        $response->assertRedirect('/lists');
        $listId = DB::table('mailing_lists')->where('user_id', $owner->id)->value('id');
        $this->assertNotNull($listId);
        $this->assertDatabaseHas('mailing_lists', [
            'id' => $listId,
            'user_id' => $owner->id,
            'name' => 'Workshop attendees',
        ]);
        $this->assertDatabaseCount('mailing_list_contacts', 2);
        $this->assertDatabaseHas('contacts', [
            'user_id' => $owner->id,
            'email_address' => 'alice@example.com',
        ]);
        $this->assertDatabaseHas('contacts', [
            'user_id' => $owner->id,
            'email_address' => 'bob@example.com',
        ]);
    }

    public function test_email_job_uses_only_the_selected_lists_contacts(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $listId = $this->createList($owner, 'Conference', 'first@example.com, second@example.com');

        $response = $this->actingAs($owner)->post('/jobs', [
            'name' => 'Conference follow-up',
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $listId,
            'subject' => 'Thanks for attending',
            'html_content' => '<p>Thank you.</p>',
            'csv_text' => 'outside-the-list@example.com',
        ]);

        $response->assertRedirect();
        $jobId = DB::table('email_jobs')->where('user_id', $owner->id)->value('id');
        $this->assertDatabaseHas('email_jobs', [
            'id' => $jobId,
            'mailing_list_id' => $listId,
            'total_recipients' => 2,
        ]);
        $this->assertDatabaseCount('email_recipients', 2);
        $this->assertDatabaseHas('email_recipients', [
            'email_job_id' => $jobId,
            'email_address' => 'first@example.com',
        ]);
        $this->assertDatabaseHas('email_recipients', [
            'email_job_id' => $jobId,
            'email_address' => 'second@example.com',
        ]);
        $this->assertDatabaseMissing('email_recipients', [
            'email_job_id' => $jobId,
            'email_address' => 'outside-the-list@example.com',
        ]);
        Queue::assertPushed(ProcessEmailJob::class);
    }

    public function test_email_job_cannot_be_created_from_unselected_addresses(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->from('/jobs/create')->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Unselected recipients',
            'html_content' => '<p>Message</p>',
            'csv_text' => 'person@example.com',
        ]);

        $response->assertRedirect('/jobs/create');
        $response->assertSessionHasErrors('mailing_list_id');
        $this->assertDatabaseCount('email_jobs', 0);
        $this->assertDatabaseCount('email_recipients', 0);
        Queue::assertNothingPushed();
    }

    public function test_open_tracking_is_off_by_default_and_can_be_enabled_when_creating_a_campaign(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $listId = $this->createList($owner, 'Campaign recipients', 'reader@example.com');

        $this->actingAs($owner)->get('/jobs/create')
            ->assertOk()
            ->assertSee('Track opens for this campaign')
            ->assertSee('name="track_opens"', false);

        $this->actingAs($owner)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $listId,
            'subject' => 'Tracked campaign',
            'html_content' => '<p>Tracked message</p>',
            'track_opens' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_jobs', [
            'user_id' => $owner->id,
            'subject' => 'Tracked campaign',
            'track_opens' => true,
        ]);

        $this->actingAs($owner)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $listId,
            'subject' => 'Untracked campaign',
            'html_content' => '<p>Untracked message</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_jobs', [
            'user_id' => $owner->id,
            'subject' => 'Untracked campaign',
            'track_opens' => false,
        ]);
    }

    public function test_user_cannot_create_a_job_from_another_users_list(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $otherListId = $this->createList($otherUser, 'Private list', 'private@example.com');

        $response = $this->actingAs($owner)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $otherListId,
            'subject' => 'Unauthorized list',
            'html_content' => '<p>Message</p>',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('email_jobs', 0);
        Queue::assertNothingPushed();
    }

    public function test_unsubscribed_contact_stays_suppressed_when_imported_into_another_list(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $this->createList($owner, 'First project', 'opted-out@example.com');
        DB::table('contacts')
            ->where('user_id', $owner->id)
            ->where('email_address', 'opted-out@example.com')
            ->update(['unsubscribed_at' => now()]);

        $secondListId = $this->createList(
            $owner,
            'New project',
            'opted-out@example.com, active@example.com',
        );

        $this->assertNotNull(DB::table('contacts')
            ->where('user_id', $owner->id)
            ->where('email_address', 'opted-out@example.com')
            ->value('unsubscribed_at'));
        $this->assertDatabaseCount('contacts', 2);

        $response = $this->actingAs($owner)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $secondListId,
            'subject' => 'New project announcement',
            'html_content' => '<p>Project news</p>',
        ]);

        $response->assertRedirect();
        $jobId = DB::table('email_jobs')->where('user_id', $owner->id)->value('id');
        $this->assertDatabaseHas('email_jobs', [
            'id' => $jobId,
            'total_recipients' => 1,
        ]);
        $this->assertDatabaseCount('email_recipients', 1);
        $this->assertDatabaseHas('email_recipients', [
            'email_job_id' => $jobId,
            'email_address' => 'active@example.com',
        ]);
        $this->assertDatabaseMissing('email_recipients', [
            'email_job_id' => $jobId,
            'email_address' => 'opted-out@example.com',
        ]);
    }

    private function createList(User $owner, string $name, string $csvText): int
    {
        $response = $this->actingAs($owner)->post('/lists', [
            'name' => $name,
            'csv_text' => $csvText,
        ]);

        $response->assertRedirect('/lists');

        return (int) DB::table('mailing_lists')->where('user_id', $owner->id)->where('name', $name)->value('id');
    }
}
