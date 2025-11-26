<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create 
                            {--email= : User email address}
                            {--name= : User name}
                            {--password= : User password (if not provided, will be prompted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $name = $this->option('name');
        $password = $this->option('password');
        $noInteraction = !$this->input->isInteractive();

        // If running non-interactively, require all options
        if ($noInteraction) {
            if (!$email || !$name || !$password) {
                $this->error('Error: --email, --name, and --password are required when running non-interactively');
                return Command::FAILURE;
            }
        } else {
            // Interactive mode: prompt for missing information
            if (!$email) {
                $email = $this->ask('Email address');
            }

            if (!$name) {
                $name = $this->ask('Name');
            }

            if (!$password) {
                $password = $this->secret('Password');
                $passwordConfirmation = $this->secret('Confirm password');
                
                if ($password !== $passwordConfirmation) {
                    $this->error('Passwords do not match!');
                    return Command::FAILURE;
                }
            }
        }

        // Validate input
        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return Command::FAILURE;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists!");
            return Command::FAILURE;
        }

        // Create user
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $this->info("User created successfully!");
            $this->line("  ID: {$user->id}");
            $this->line("  Name: {$user->name}");
            $this->line("  Email: {$user->email}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create user: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
