<?php

namespace Tests\Smoke;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Smoke test: Application boots successfully
     */
    public function test_application_boots_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302); // Redirects to login/dashboard
    }

    /**
     * Smoke test: Database connection works
     */
    public function test_database_connection_works(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Smoke test: Authentication routes are accessible
     */
    public function test_authentication_routes_are_accessible(): void
    {
        $loginResponse = $this->get('/login');
        $registerResponse = $this->get('/register');

        $loginResponse->assertStatus(200);
        $registerResponse->assertStatus(200);
    }

    /**
     * Smoke test: Protected routes require authentication
     */
    public function test_protected_routes_require_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * Smoke test: Queue system is configured
     */
    public function test_queue_system_is_configured(): void
    {
        $queueConnection = config('queue.default');

        $this->assertNotEmpty($queueConnection);
        $this->assertArrayHasKey($queueConnection, config('queue.connections'));
    }

    /**
     * Smoke test: Email configuration exists
     */
    public function test_email_configuration_exists(): void
    {
        $rateLimit = config('email.rate_limit.seconds');

        $this->assertNotNull($rateLimit);
        $this->assertGreaterThan(0, (int) $rateLimit);
    }
}
