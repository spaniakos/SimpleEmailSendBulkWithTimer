<?php

namespace Tests\Feature;

use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_smtp_configurations_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/smtp');

        $response->assertStatus(200);
        $response->assertViewIs('smtp.index');
    }

    public function test_user_can_create_smtp_configuration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/smtp', [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => 'password123',
            'encryption' => 'tls',
            'from_email' => 'test@example.com',
            'from_name' => 'Test Sender',
        ]);

        $response->assertRedirect('/smtp');
        $this->assertDatabaseHas('smtp_configurations', [
            'user_id' => $user->id,
            'host' => 'smtp.example.com',
            'port' => 587,
        ]);
    }

    public function test_user_can_view_their_own_smtp_configuration(): void
    {
        $user = User::factory()->create();
        $config = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/smtp/{$config->id}");

        $response->assertStatus(200);
        $response->assertViewIs('smtp.show');
        $response->assertViewHas('smtpConfiguration', $config);
    }

    public function test_user_cannot_view_other_users_smtp_configuration(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $config = SmtpConfiguration::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->get("/smtp/{$config->id}");

        $response->assertForbidden();
    }

    public function test_user_can_update_their_own_smtp_configuration(): void
    {
        $user = User::factory()->create();
        $config = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/smtp/{$config->id}", [
            'name' => 'Updated SMTP',
            'host' => 'smtp.updated.com',
            'port' => 465,
            'username' => 'updated@example.com',
            'password' => 'newpassword',
            'encryption' => 'ssl',
            'from_email' => 'updated@example.com',
            'from_name' => 'Updated Sender',
        ]);

        $response->assertRedirect('/smtp');
        $this->assertDatabaseHas('smtp_configurations', [
            'id' => $config->id,
            'host' => 'smtp.updated.com',
            'port' => 465,
        ]);
    }

    public function test_user_cannot_update_other_users_smtp_configuration(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $config = SmtpConfiguration::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->put("/smtp/{$config->id}", [
            'host' => 'hacked.com',
            'port' => 587,
            'username' => 'hacker@example.com',
            'password' => 'hacked',
            'encryption' => 'tls',
            'from_email' => 'hacker@example.com',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_their_own_smtp_configuration(): void
    {
        $user = User::factory()->create();
        $config = SmtpConfiguration::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/smtp/{$config->id}");

        $response->assertRedirect('/smtp');
        $this->assertDatabaseMissing('smtp_configurations', ['id' => $config->id]);
    }

    public function test_user_cannot_delete_other_users_smtp_configuration(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $config = SmtpConfiguration::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->delete("/smtp/{$config->id}");

        $response->assertForbidden();
    }

    public function test_smtp_configuration_password_is_encrypted(): void
    {
        $user = User::factory()->create();
        $password = 'secretpassword123';

        $this->actingAs($user)->post('/smtp', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => $password,
            'encryption' => 'tls',
            'from_email' => 'test@example.com',
        ]);

        $config = SmtpConfiguration::where('user_id', $user->id)->first();
        
        // Password should be encrypted in database
        $this->assertNotEquals($password, $config->getAttributes()['password']);
        
        // But accessible decrypted via accessor
        $this->assertEquals($password, $config->password);
    }
}
