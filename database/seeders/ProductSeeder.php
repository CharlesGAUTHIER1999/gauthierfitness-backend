<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::inRandomOrder()->first();

        $catalogue = [

            // ================= FEMMES =================
            'femmes' => [
                'femmes-leggings' => [
                    'Legging Sculpt',
                    'Legging Seamless',
                    'Legging Performance',
                ],
                'femmes-jogging' => [
                    'Jogging Training',
                    'Jogging Comfort',
                    'Jogging Performance',
                ],
                'femmes-sweats' => [
                    'Sweat Femme Zippé',
                    'Sweat Femme Oversize',
                    'Sweat Femme Training',
                ],
                'femmes-vestes' => [
                    'Veste Training Femme',
                    'Veste Coupe-Vent Femme',
                    'Veste Thermique Femme',
                ],
                'femmes-shorts' => [
                    'Short Femme Training',
                    'Short Femme Seamless',
                    'Short Femme Performance',
                ],
                'femmes-brassieres' => [
                    'Brassière Impact',
                    'Brassière Comfort',
                    'Brassière Training',
                ],
                'femmes-tshirts' => [
                    'T-shirt Training Femme',
                    'T-shirt Oversize Femme',
                    'T-shirt Performance Femme',
                ],
                'femmes-accessoires' => [
                    'Sac de sport Femme',
                    'Gourde Fitness',
                    'Bandeau Training',
                ],
            ],

            // ================= HOMMES =================
            'hommes' => [
                'hommes-accessoires' => [
                    'Sac de sport Homme',
                    'Ceinture de musculation',
                    'Gants Training',
                ],
                'hommes-sweats' => [
                    'Sweat Zippé Performance',
                    'Hoodie Training',
                    'Sweat Oversize',
                ],
                'hommes-vestes' => [
                    'Veste Training Homme',
                    'Veste Coupe-Vent Homme',
                    'Veste Thermique Homme',
                ],
                'hommes-pantalons' => [
                    'Pantalon Training',
                    'Pantalon Jogging',
                    'Pantalon Performance',
                ],
                'hommes-shorts' => [
                    'Short Training',
                    'Short Oversize',
                    'Short Performance',
                ],
                'hommes-tshirts' => [
                    'T-shirt Training Homme',
                    'T-shirt Oversize Homme',
                    'T-shirt Performance Homme',
                ],
            ],

            // ================= NUTRITION =================
            'nutrition' => [
                'nutrition-proteines-proteines-poudre' => [
                    'Whey Classic Vanille',
                    'Whey Classic Chocolat',
                    'Whey Classic Fraise',
                ],
                'nutrition-proteines-isolats' => [
                    'Whey Isolate Vanille',
                    'Whey Isolate Chocolat',
                    'Whey Isolate Neutre',
                ],
                'nutrition-proteines-hydrolysees' => [
                    'Whey Hydrolysée',
                    'Hydrolysat Performance',
                    'Hydro Whey Pro',
                ],
                'nutrition-proteines-barres' => [
                    'Barre Protéinée Chocolat',
                    'Barre Protéinée Vanille',
                    'Barre Protéinée Cookies',
                ],
                'nutrition-performance-masse' => [
                    'Gainer Mass Pro',
                    'Gainer Hard Mass',
                    'Gainer Clean Mass',
                ],
                'nutrition-performance-creatine' => [
                    'Créatine Monohydrate',
                    'Créatine Creapure',
                    'Créatine Capsules',
                ],
                'nutrition-performance-preworkout' => [
                    'Pre Workout Xtreme',
                    'Pre Workout Focus',
                    'Pre Workout Energy',
                ],
                'nutrition-performance-boissons' => [
                    'Boisson Isotonique',
                    'Energy Drink Training',
                    'Recovery Drink',
                ],
            ],

            // ================= ÉQUIPEMENTS =================
            'equipements' => [
                'equipements-barres' => [
                    'Barre Olympique 20kg',
                    'Barre Olympique 15kg',
                    'Barre EZ',
                ],
                'equipements-musculation' => [
                    'Banc de musculation réglable',
                    'Rack Squat Pro',
                    'Presse à jambes',
                ],
                'equipements-rigs' => [
                    'Rig Cross Training',
                    'Rack Power Cage',
                    'Rig Wall Mounted',
                ],
                'equipements-prepa' => [
                    'Rameur Indoor',
                    'Air Bike',
                    'Ski Erg',
                ],
                'equipements-calisthenie' => [
                    'Anneaux Gym',
                    'Barre de traction murale',
                    'Parallettes',
                ],
                'equipements-bandes' => [
                    'Bandes élastiques light',
                    'Bandes élastiques medium',
                    'Bandes élastiques strong',
                ],
                'equipements-mobilite' => [
                    'Tapis de sol',
                    'Foam Roller',
                    'Gym Ball',
                ],
            ],
        ];

        foreach ($catalogue as $root => $categories) {
            foreach ($categories as $categorySlug => $products) {
                $category = Category::where('slug', $categorySlug)->first();

                if (!$category) {
                    continue;
                }

                foreach ($products as $name) {

                    $priceHt = rand(2000, 15000) / 100;
                    $vat = 20;

                    $product = Product::create([
                        'supplier_id' => $supplier->id,
                        'name' => $name,
                        'slug' => Str::slug($name) . '-' . rand(100, 999),
                        'description' => "Description détaillée de $name.",
                        'price_ht' => $priceHt,
                        'price_ttc' => $priceHt * (1 + $vat / 100),
                        'vat' => $vat,
                        'sku' => strtoupper(Str::slug($name)) . '-' . rand(1000, 9999),
                        'attributes' => $this->attributesFor($root),
                        'is_active' => true,
                    ]);

                    $product->categories()->attach($category->id);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => 'https://picsum.photos/600/600?random=' . rand(1, 9999),
                        'is_main' => true,
                    ]);
                }
            }
        }
    }

    private function attributesFor(string $root): ?array
    {
        return match ($root) {
            'femmes', 'hommes' => [
                'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
                'colors' => ['noir', 'blanc', 'beige'],
            ],
            'nutrition' => [
                'formats' => ['500g', '1kg', '2kg'],
            ],
            default => null,
        };
    }
}