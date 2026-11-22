<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('XOOSE_ADMIN_EMAIL', 'admin@xoose.local')],
            [
                'name' => env('XOOSE_ADMIN_NAME', 'Xoose Admin'),
                'password' => Hash::make(env('XOOSE_ADMIN_PASSWORD', 'password123')),
                'email_verified_at' => now(),
            ]
        );
    }
}
