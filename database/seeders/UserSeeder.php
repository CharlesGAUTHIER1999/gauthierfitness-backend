<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run():void {
        // Admin
        $admin = User::factory()->create([
            'email' => 'admin@fitness.com',
            'password' => Hash::make('admin123'),
        ]);
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        // Clients
        User::factory(20)->create()
            ->each(function ($u) {
                $u->roles()->attach(Role::where('name', 'client')->first());
            });
    }
}