<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /** Reset roles and create the base "admin" role. */
    public function run(): void
    {
        Role::query()->delete();
        Role::updateOrCreate(['name' => 'admin']);
    }
}
