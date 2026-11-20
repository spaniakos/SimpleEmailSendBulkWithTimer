<?php

namespace Tests\Feature;

use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_email_jobs_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/jobs');

        $response->assertStatus(200);
        $response->assertViewIs('jobs.index');
    }

    public function test_user_can_create_email_job_from_a_selected_list(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $listId = $this->createList($user, 'Test audience', 'email1@example.com, email2@example.com, email3@example.com');

        $response = $this->actingAs($user)->post('/jobs', [
            'name' => 'Test Job',
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Test Subject',
            'html_content' => '<h1>Test Content</h1>',
            'mailing_list_id' => $listId,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('email_jobs', [
            'user_id' => $user->id,
            'subject' => 'Test Subject',
            'total_recipients' => 3,
        ]);

        $this->assertDatabaseCount('email_recipients', 3);
    }

    public function test_user_can_create_a_list_from_a_csv_file_and_send_to_that_list(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->createWithContent('emails.csv', "email1@example.com\nemail2@example.com\nemail3@example.com");

        $this->actingAs($user)->post('/lists', [
            'name' => 'Imported audience',
            'csv_file' => $file,
        ])->assertRedirect('/lists');
        $listId = DB::table('mailing_lists')->where('user_id', $user->id)->value('id');

        $response = $this->actingAs($user)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $listId,
            'subject' => 'Test Subject',
            'html_content' => '<h1>Test Content</h1>',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('email_jobs', [
            'user_id' => $user->id,
            'total_recipients' => 3,
        ]);
    }

    public function test_user_can_view_their_own_email_job(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);

        $response = $this->actingAs($user)->get("/jobs/{$job->id}");

        $response->assertStatus(200);
        $response->assertViewIs('jobs.show');
    }

    public function test_email_preview_sandboxes_user_supplied_html(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $payload = '<img src=x onerror="alert(document.domain)"><script>alert(document.domain)</script>';
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
            'html_content' => $payload,
        ]);

        $response = $this->actingAs($user)->get("/jobs/{$job->id}");

        $response->assertOk();
        $response->assertSee('<iframe', escape: false);
        $response->assertSee('sandbox=""', escape: false);
        $response->assertDontSee($payload, escape: false);
        $response->assertSee('&lt;script&gt;alert(document.domain)&lt;/script&gt;');
    }

    public function test_failed_recipient_error_is_available_in_expandable_details(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create([
            'user_id' => $user->id,
            'username' => 'smtp-account@example.test',
            'password' => 'smtp-secret-for-test',
        ]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);
        $error = '550 5.7.1 Recipient policy rejected this message for smtp-account@example.test (smtp-secret-for-test). '.str_repeat('More diagnostic detail. ', 6).'<script>alert(1)</script>';

        EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
            'email_address' => 'blocked@example.com',
            'domain' => 'example.com',
            'status' => 'failed',
            'error_message' => $error,
        ]);

        $response = $this->actingAs($user)->get("/jobs/{$job->id}?status=failed");

        $response->assertOk();
        $response->assertSee('<details', escape: false);
        $response->assertSee('Show error details');
        $response->assertSee('550 5.7.1 Recipient policy rejected this message for [redacted] ([redacted]).');
        $response->assertSee('[redacted]');
        $response->assertDontSee('smtp-secret-for-test');
        $response->assertDontSee('smtp-account@example.test');
        $response->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_user_cannot_view_other_users_email_job(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user2->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user2->id,
            'smtp_configuration_id' => $smtp->id,
        ]);

        $response = $this->actingAs($user1)->get("/jobs/{$job->id}");

        $response->assertForbidden();
    }

    public function test_user_can_update_their_own_email_job(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);

        $response = $this->actingAs($user)->put("/jobs/{$job->id}", [
            'name' => 'Updated Job',
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Updated Subject',
            'html_content' => '<h1>Updated Content</h1>',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('email_jobs', [
            'id' => $job->id,
            'subject' => 'Updated Subject',
        ]);
    }

    public function test_user_can_delete_their_own_email_job(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);

        $response = $this->actingAs($user)->delete("/jobs/{$job->id}");

        $response->assertRedirect('/jobs');
        $this->assertDatabaseMissing('email_jobs', ['id' => $job->id]);
    }

    public function test_user_can_pause_email_job(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($user)->post("/jobs/{$job->id}/pause");

        $response->assertRedirect();
        $this->assertDatabaseHas('email_jobs', [
            'id' => $job->id,
            'status' => 'paused',
        ]);
    }

    public function test_user_can_resume_paused_email_job(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
            'status' => 'paused',
        ]);

        $response = $this->actingAs($user)->post("/jobs/{$job->id}/resume");

        $response->assertRedirect();
        $this->assertDatabaseHas('email_jobs', [
            'id' => $job->id,
            'status' => 'processing',
        ]);
    }

    public function test_list_import_extracts_unique_normalized_emails(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/lists', [
            'name' => 'Import test',
            'csv_text' => 'email1@example.com, email2@example.com, email1@example.com', // duplicate
        ]);

        $response->assertRedirect('/lists');
        $this->assertDatabaseCount('mailing_list_contacts', 2);
    }

    public function test_email_job_extracts_domains_correctly(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $listId = $this->createList($user, 'Domain test', 'user1@gmail.com, user2@yahoo.com');

        $this->actingAs($user)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $listId,
            'subject' => 'Test',
            'html_content' => '<p>Test</p>',
        ]);

        $recipient1 = EmailRecipient::where('email_address', 'user1@gmail.com')->first();
        $recipient2 = EmailRecipient::where('email_address', 'user2@yahoo.com')->first();

        $this->assertEquals('gmail.com', $recipient1->domain);
        $this->assertEquals('yahoo.com', $recipient2->domain);
    }

    private function createList(User $user, string $name, string $csvText): int
    {
        $this->actingAs($user)->post('/lists', [
            'name' => $name,
            'csv_text' => $csvText,
        ])->assertRedirect('/lists');

        return (int) DB::table('mailing_lists')->where('user_id', $user->id)->where('name', $name)->value('id');
    }
}
