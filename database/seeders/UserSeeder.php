<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /** Create the admin/dev account, attach the admin role, and generate random users. */
    public function run(): void
    {
        // Admin/dev account
        $admin = User::updateOrCreate(
            ['email' => 'charles.gauthier99@gmail.com'],
            [
                'firstname' => 'Charles',
                'lastname' => 'Gauthier',
                'password' => Hash::make(env('ADMIN_SEED_PASSWORD', Str::random(24))),
                'phone' => null,
                'is_b2b' => false,
                'company_name' => null,
                'address' => '34 Rue du Vélodrome',
                'city' => 'Bordeaux',
                'zip' => '33200',
                'email_verified_at' => now(),
            ]
        );

        // Attach admin role (idempotent)
        $admin_role = Role::where('name', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$admin_role->id]);

        // Random users (no roles)
        User::factory()->count(10)->create();
    }
}
