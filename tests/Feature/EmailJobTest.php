<?php

namespace Tests\Feature;

use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_user_can_create_email_job_with_csv_text(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/jobs', [
            'name' => 'Test Job',
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Test Subject',
            'html_content' => '<h1>Test Content</h1>',
            'csv_text' => 'email1@example.com, email2@example.com, email3@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('email_jobs', [
            'user_id' => $user->id,
            'subject' => 'Test Subject',
            'total_recipients' => 3,
        ]);
        
        $this->assertDatabaseCount('email_recipients', 3);
    }

    public function test_user_can_create_email_job_with_csv_file(): void
    {
        Storage::fake('local');
        
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->createWithContent('emails.csv', "email1@example.com\nemail2@example.com\nemail3@example.com");

        $response = $this->actingAs($user)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Test Subject',
            'html_content' => '<h1>Test Content</h1>',
            'csv_file' => $file,
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

    public function test_csv_parsing_extracts_unique_emails(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Test',
            'html_content' => '<p>Test</p>',
            'csv_text' => 'email1@example.com, email2@example.com, email1@example.com', // duplicate
        ]);

        $response->assertRedirect();
        $job = EmailJob::where('user_id', $user->id)->first();
        
        // Should only have 2 unique emails
        $this->assertEquals(2, $job->total_recipients);
    }

    public function test_email_job_extracts_domains_correctly(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post('/jobs', [
            'smtp_configuration_id' => $smtp->id,
            'subject' => 'Test',
            'html_content' => '<p>Test</p>',
            'csv_text' => 'user1@gmail.com, user2@yahoo.com',
        ]);

        $recipient1 = EmailRecipient::where('email_address', 'user1@gmail.com')->first();
        $recipient2 = EmailRecipient::where('email_address', 'user2@yahoo.com')->first();

        $this->assertEquals('gmail.com', $recipient1->domain);
        $this->assertEquals('yahoo.com', $recipient2->domain);
    }
}
