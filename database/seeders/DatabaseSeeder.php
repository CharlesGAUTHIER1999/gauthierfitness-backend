<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            SupplierSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            ProductGroupSeeder::class,
            ProductOptionSeeder::class,
            StockSeeder::class,
            OrderSeeder::class,
            SupportSeeder::class,
        ]);
    }
}

