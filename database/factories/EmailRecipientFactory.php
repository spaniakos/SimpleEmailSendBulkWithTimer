<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailRecipient>
 */
class EmailRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->email();
        $domain = substr(strrchr($email, '@'), 1);

        return [
            'email_job_id' => \App\Models\EmailJob::factory(),
            'email_address' => $email,
            'domain' => $domain,
            'status' => fake()->randomElement(['pending', 'sent', 'failed']),
            'sent_at' => fake()->optional()->dateTime(),
            'error_message' => fake()->optional()->sentence(),
        ];
    }
}
