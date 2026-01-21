<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Compte admin/dev
        User::updateOrCreate(
            ['email' => 'charles.gauthier99@gmail.com'],
            [
                'firstname' => 'Charles',
                'lastname'  => 'Gauthier',
                'password'  => Hash::make('password'),
                'phone'     => null,
                'is_b2b'    => false,
                'company_name' => null,

                // ✅ IMPORTANT : adresse non-null
                'address' => '10 Rue de la Paix',
                'city'    => 'Paris',
                'zip'     => '75002',

                'email_verified_at' => now(),
            ]
        );

        // Quelques users random
        User::factory()->count(10)->create();
    }
}
