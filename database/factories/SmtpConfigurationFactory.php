<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SmtpConfiguration>
 */
class SmtpConfigurationFactory extends Factory
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
            'name' => fake()->words(2, true) . ' SMTP',
            'host' => 'smtp.' . fake()->domainName(),
            'port' => fake()->randomElement([587, 465, 25]),
            'username' => fake()->email(),
            'password' => fake()->password(),
            'encryption' => fake()->randomElement(['tls', 'ssl']),
            'from_email' => fake()->email(),
            'from_name' => fake()->name(),
        ];
    }
}
