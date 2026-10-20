<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@signnow.local')],
            [
                'name' => 'Administrator',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ]
        );

        $admin->forceFill(['is_admin' => true])->save();
    }
}
