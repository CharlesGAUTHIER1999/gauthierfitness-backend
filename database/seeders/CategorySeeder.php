<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // 🔒 Désactivation temporaire des FK
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('product_category')->truncate();
        Category::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $categories = [
            'femmes' => [
                'type' => 'clothing',
                'children' => [
                    'leggings',
                    'jogging',
                    'sweats',
                    'vestes',
                    'shorts',
                    'brassieres',
                    'tshirts',
                    'hauts',
                    'manches-longues',
                    'combinaisons',
                    'accessoires',
                    'chaussettes',
                ],
            ],
            'hommes' => [
                'type' => 'clothing',
                'children' => [
                    'accessoires',
                    'sweats',
                    'vestes',
                    'pantalons',
                    'manches-longues',
                    'shorts',
                    'debardeurs',
                    'tshirts',
                    'chaussettes',
                ],
            ],
            'nutrition' => [
                'type' => 'nutrition',
                'children' => [
                    'proteines' => [
                        'proteines-poudre',
                        'isolats',
                        'hydrolysees',
                        'oeuf',
                        'soja',
                        'viande',
                        'vegetales',
                        'barres',
                    ],
                    'performance' => [
                        'masse',
                        'creatine',
                        'preworkout',
                        'boissons',
                    ],
                ],
            ],
            'equipements' => [
                'type' => 'equipment',
                'children' => [
                    'barres',
                    'musculation',
                    'rigs',
                    'prepa',
                    'calisthenie',
                    'bandes',
                    'mobilite',
                ],
            ],
        ];

        foreach ($categories as $root => $config) {

            // 🔹 Catégorie racine (Femmes, Hommes, Nutrition, Équipements)
            $rootCategory = Category::create([
                'name' => ucfirst($root),
                'slug' => $root,
                'type' => $config['type'],
                'position' => 0,
            ]);

            foreach ($config['children'] as $key => $value) {

                // 🔹 Cas nutrition : sous-catégorie niveau 2 (proteines, performance)
                if (is_array($value)) {

                    $parent = Category::create([
                        'name' => ucfirst($key),
                        'slug' => $root . '-' . $key, // ex: nutrition-proteines
                        'type' => $config['type'],
                        'parent_id' => $rootCategory->id,
                        'position' => 0,
                    ]);

                    foreach ($value as $child) {
                        Category::create([
                            'name' => ucfirst(str_replace('-', ' ', $child)),
                            'slug' => $root . '-' . $key . '-' . $child,
                            // ex: nutrition-proteines-isolats
                            'type' => $config['type'],
                            'parent_id' => $parent->id,
                            'position' => 0,
                        ]);
                    }

                    // 🔹 Cas standard : enfant direct du root
                } else {

                    Category::create([
                        'name' => ucfirst(str_replace('-', ' ', $value)),
                        'slug' => $root . '-' . $value,
                        // ex: femmes-accessoires / hommes-accessoires
                        'type' => $config['type'],
                        'parent_id' => $rootCategory->id,
                        'position' => 0,
                    ]);
                }
            }
        }
    }
}
