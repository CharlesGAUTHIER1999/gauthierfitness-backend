<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /** Truncate categories and seed the root/child category tree. */
    public function run(): void
    {
        // Temporarily disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_category')->truncate();
        Category::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $categories = [
            'femmes' => [
                'type' => 'clothing',
                'children' => [
                    'pantalons',
                    'sweats',
                    'vestes',
                    'tshirts',
                ],
            ],
            'hommes' => [
                'type' => 'clothing',
                'children' => [
                    'sweats',
                    'vestes',
                    'pantalons',
                    'tshirts',
                ],
            ],

            'nutrition' => [
                'type' => 'nutrition',
                'children' => [
                    'proteines-poudre',
                    'isolats',
                    'barres',
                    'creatine',
                    'boissons',
                ],
            ],

            'equipments' => [
                'type' => 'equipment',
                'children' => [
                    'barres',
                    'musculation',
                    'prepa',
                    'calisthenie',
                    'mobilite',
                ],
            ],
        ];

        foreach ($categories as $root => $config) {

            $root_category = Category::create([
                'name' => ucfirst($root),
                'slug' => $root,
                'type' => $config['type'],
                'position' => 0,
                'parent_id' => null,
            ]);

            foreach ($config['children'] as $child_slug) {
                Category::create([
                    'name' => ucfirst(str_replace('-', ' ', $child_slug)),
                    'slug' => $root.'-'.$child_slug,
                    'type' => $config['type'],
                    'parent_id' => $root_category->id,
                    'position' => 0,
                ]);
            }
        }
    }
}
