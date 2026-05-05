<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@verdeo.com.ar'],
            [
                'name'     => 'Admin Verdeo',
                'password' => Hash::make('verdeo2026'),
                'role'     => 'admin',
            ]
        );
    }
}
