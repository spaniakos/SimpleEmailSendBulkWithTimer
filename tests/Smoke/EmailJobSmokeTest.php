<?php

namespace Tests\Smoke;

use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailJobSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Smoke test: Email job model can be created
     */
    public function test_email_job_model_can_be_created(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);

        $this->assertDatabaseHas('email_jobs', [
            'id' => $job->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Smoke test: Email recipients can be created
     */
    public function test_email_recipients_can_be_created(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);

        $recipient = EmailRecipient::factory()->create([
            'email_job_id' => $job->id,
        ]);

        $this->assertDatabaseHas('email_recipients', [
            'id' => $recipient->id,
            'email_job_id' => $job->id,
        ]);
    }

    /**
     * Smoke test: SMTP configuration can be created
     */
    public function test_smtp_configuration_can_be_created(): void
    {
        $user = User::factory()->create();

        $smtp = SmtpConfiguration::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('smtp_configurations', [
            'id' => $smtp->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Smoke test: Email job relationships work
     */
    public function test_email_job_relationships_work(): void
    {
        $user = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $user->id]);
        $job = EmailJob::factory()->create([
            'user_id' => $user->id,
            'smtp_configuration_id' => $smtp->id,
        ]);
        EmailRecipient::factory()->count(3)->create(['email_job_id' => $job->id]);

        $this->assertInstanceOf(User::class, $job->user);
        $this->assertInstanceOf(SmtpConfiguration::class, $job->smtpConfiguration);
        $this->assertCount(3, $job->recipients);
    }
}
