<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'admin_status' => false,
        ]);

        User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'admin_status' => false,
        ]);

        User::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);
    }
}