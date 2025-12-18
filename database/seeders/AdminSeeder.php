<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin already exists
        if (User::where('email', 'admin@zenbook.com')->exists()) {
            $this->command->info('Admin account already exists!');
            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => 'admin@zenbook.com',
            'password' => Hash::make('admin123'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin account created successfully!');
        $this->command->info('Email: admin@zenbook.com');
        $this->command->info('Password: admin123');
    }
}

