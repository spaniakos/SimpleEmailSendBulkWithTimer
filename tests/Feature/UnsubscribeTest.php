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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_email_has_a_customizable_unsubscribe_footer_and_one_click_headers(): void
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
        DB::table('email_settings')->insert([
            'user_id' => $owner->id,
            'unsubscribe_footer_text' => 'Publisher notice <thanks>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'mailing_list_id' => $list->id,
            'status' => 'processing',
            'total_recipients' => 1,
            'html_content' => '<p>Campaign message</p>',
        ]);
        EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.com',
            'status' => 'pending',
        ]);

        (new ProcessEmailJob($job, $smtp))->handle();

        $sentMessage = null;
        Mail::assertSent('App\\Mail\\CampaignEmail', function ($message) use (&$sentMessage): bool {
            $sentMessage = $message;

            return true;
        });

        $html = $sentMessage->render();
        $this->assertStringContainsString('Publisher notice &lt;thanks&gt;', $html);
        $this->assertStringContainsString('Campaign message', $html);
        $this->assertStringNotContainsString($contact->email_address, $html);
        $this->assertMatchesRegularExpression('#/unsubscribe/[a-f0-9]{64}#', $html);

        preg_match('#/unsubscribe/([a-f0-9]{64})#', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertDatabaseHas('unsubscribe_tokens', [
            'contact_id' => $contact->id,
            'token_hash' => hash('sha256', $matches[1]),
            'used_at' => null,
        ]);
        $this->assertDatabaseMissing('unsubscribe_tokens', [
            'token_hash' => $matches[1],
        ]);

        $headers = $sentMessage->headers()->text;
        $this->assertSame('<'.route('unsubscribe.confirm', $matches[1]).'>', $headers['List-Unsubscribe'] ?? null);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post'] ?? null);
    }

    public function test_get_confirmation_does_not_unsubscribe_but_one_click_post_is_single_use(): void
    {
        $owner = User::factory()->create();
        $contact = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'subscriber@example.com',
        ]);
        $token = bin2hex(random_bytes(32));
        DB::table('unsubscribe_tokens')->insert([
            'contact_id' => $contact->id,
            'token_hash' => hash('sha256', $token),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $confirmation = $this->get("/unsubscribe/{$token}");

        $confirmation->assertOk();
        $confirmation->assertSee('Unsubscribe');
        $this->assertNull($contact->fresh()->unsubscribed_at);
        $this->assertDatabaseHas('unsubscribe_tokens', [
            'token_hash' => hash('sha256', $token),
            'used_at' => null,
        ]);

        $response = $this->post("/unsubscribe/{$token}", [
            'List-Unsubscribe' => 'One-Click',
        ]);

        $response->assertOk();
        $this->assertNotNull($contact->fresh()->unsubscribed_at);
        $this->assertNotNull(DB::table('unsubscribe_tokens')->where('token_hash', hash('sha256', $token))->value('used_at'));
        $this->post("/unsubscribe/{$token}", ['List-Unsubscribe' => 'One-Click'])->assertStatus(410);
    }

    public function test_user_can_update_their_account_unsubscribe_footer_text(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/settings/email')->assertOk()->assertSee('Unsubscribe footer text');

        $response = $this->actingAs($owner)->put('/settings/email', [
            'unsubscribe_footer_text' => 'Contact preferences are yours to change.',
        ]);

        $response->assertRedirect('/settings/email');
        $this->assertDatabaseHas('email_settings', [
            'user_id' => $owner->id,
            'unsubscribe_footer_text' => 'Contact preferences are yours to change.',
        ]);
    }

    public function test_queued_recipient_is_suppressed_if_the_contact_unsubscribes_before_delivery(): void
    {
        Mail::fake();
        Cache::flush();
        $owner = User::factory()->create();
        $smtp = SmtpConfiguration::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::create([
            'user_id' => $owner->id,
            'email_address' => 'subscriber@example.com',
            'unsubscribed_at' => now(),
        ]);
        $job = EmailJob::factory()->create([
            'user_id' => $owner->id,
            'smtp_configuration_id' => $smtp->id,
            'status' => 'processing',
            'total_recipients' => 1,
        ]);
        EmailRecipient::create([
            'email_job_id' => $job->id,
            'contact_id' => $contact->id,
            'email_address' => $contact->email_address,
            'domain' => 'example.com',
            'status' => 'pending',
        ]);

        (new ProcessEmailJob($job, $smtp))->handle();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('email_recipients', [
            'email_job_id' => $job->id,
            'status' => 'suppressed',
        ]);
        $this->assertNotNull(DB::table('email_recipients')->where('email_job_id', $job->id)->value('suppressed_at'));
        $this->assertDatabaseHas('email_jobs', [
            'id' => $job->id,
            'suppressed_count' => 1,
        ]);
    }
}
