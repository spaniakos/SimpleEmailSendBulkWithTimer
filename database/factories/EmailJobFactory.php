<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailJob>
 */
class EmailJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'smtp_configuration_id' => \App\Models\SmtpConfiguration::factory(),
            'name' => fake()->words(3, true).' Job',
            'subject' => fake()->sentence(),
            'html_content' => '<h1>'.fake()->sentence().'</h1><p>'.fake()->paragraph().'</p>',
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed', 'paused']),
            'total_recipients' => fake()->numberBetween(1, 100),
            'sent_count' => 0,
            'failed_count' => 0,
        ];
    }
}
