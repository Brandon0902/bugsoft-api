<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'brandon09@gmail.com'],
            [
                'name' => 'brandon',
                'password' => Hash::make('brandon0902'),
                'role' => 'super_admin',
                'status' => true,
                'clinic_id' => null, // super_admin no pertenece a una clínica
            ]
        );
    }
}