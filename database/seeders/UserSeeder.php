<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample users for testing
        $users = [
            [
                'first_name' => 'John',
                'middle_name' => 'Michael',
                'last_name' => 'Doe',
                'name' => 'John Michael Doe',
                'email' => 'john.doe@example.com',
                'phone' => 61412345678,
                'country_code' => '+61',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
            [
                'first_name' => 'Jane',
                'middle_name' => null,
                'last_name' => 'Smith',
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'phone' => 61487654321,
                'country_code' => '+61',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
            [
                'first_name' => 'Robert',
                'middle_name' => 'James',
                'last_name' => 'Johnson',
                'name' => 'Robert James Johnson',
                'email' => 'robert.johnson@example.com',
                'phone' => 61411223344,
                'country_code' => '+61',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
            [
                'first_name' => 'Sarah',
                'middle_name' => 'Elizabeth',
                'last_name' => 'Williams',
                'name' => 'Sarah Elizabeth Williams',
                'email' => 'sarah.williams@example.com',
                'phone' => 61455667788,
                'country_code' => '+61',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
            [
                'first_name' => 'Michael',
                'middle_name' => null,
                'last_name' => 'Brown',
                'name' => 'Michael Brown',
                'email' => 'michael.brown@example.com',
                'phone' => 61499887766,
                'country_code' => '+61',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }

        $this->command->info('Sample users created successfully!');
        $this->command->info('You can login with any of these emails using password: password123');
    }
}
