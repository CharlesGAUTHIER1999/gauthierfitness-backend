<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::create([
            'name' => 'Tsunami Nutrition',
            'slug' => Str::slug('Tsunami Nutrition'),
            'address' => 'Via Torino 12, Milano, IT',
            'contact_email' => 'support@tsunaminutrition.com',
            'phone' => '+39 045 445 998',
        ]);

        Supplier::create([
            'name' => 'Rogue Fitness',
            'slug' => Str::slug('Rogue Fitness'),
            'address' => '545 E 5th Ave, Columbus, USA',
            'contact_email' => 'contact@roguefitness.com',
            'phone' => '+1 614-358-6190',
        ]);

        Supplier::create([
            'name' => 'MyProtein',
            'slug' => Str::slug('MyProtein'),
            'address' => 'Manchester, UK',
            'contact_email' => 'info@myprotein.com',
            'phone' => '+44 161 813 1487',
        ]);
    }
}