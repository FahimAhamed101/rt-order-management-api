<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class PassportClientSeeder extends Seeder
{
    public function run(): void
    {
        // Check if clients already exist
        if (Client::count() > 0) {
            $this->command->info('Passport clients already exist. Skipping...');
            return;
        }

        // Create Personal Access Client
        Client::create([
            'name' => 'Personal Access Client',
            'secret' => \Illuminate\Support\Str::random(40),
            'provider' => null,
            'redirect' => 'http://localhost',
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
        ]);

        // Create Password Grant Client
        Client::create([
            'name' => 'Password Grant Client',
            'secret' => \Illuminate\Support\Str::random(40),
            'provider' => 'users',
            'redirect' => 'http://localhost',
            'personal_access_client' => false,
            'password_client' => true,
            'revoked' => false,
        ]);

        $this->command->info('Passport clients created successfully!');
        $this->command->info('Please update your .env file with these credentials:');
        
        $clients = Client::all();
        foreach ($clients as $client) {
            $type = $client->personal_access_client ? 'Personal Access' : 'Password Grant';
            $this->command->info("{$type} Client - ID: {$client->id}, Secret: {$client->secret}");
        }
    }
}