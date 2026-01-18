<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProductGroupSeeder extends Seeder
{
    private ?int $skuMax = null;
    private ?int $slugMax = null;

    public function run(): void
    {
        $this->disableFk();

        // Reset group and fields
        DB::table('products')->update([
            'group_id'    => null,
            'color_code'  => null,
            'color_label' => null,
        ]);

        DB::table('product_groups')->truncate();

        $this->enableFk();

        // Cache tailles réelles des colonnes
        $this->skuMax  = $this->getColumnMaxLen('products', 'sku', 80);
        $this->slugMax = $this->getColumnMaxLen('products', 'slug', 255);

        // --- Palette (code + label) ---
        $c = [
            'black'         => ['code' => 'black', 'label' => 'Noir'],
            'white'         => ['code' => 'white', 'label' => 'Blanc'],
            'grey'          => ['code' => 'grey', 'label' => 'Gris'],
            'blue'          => ['code' => 'blue', 'label' => 'Bleu'],
            'navy'          => ['code' => 'navy', 'label' => 'Bleu foncé'],
            'brown'         => ['code' => 'brown', 'label' => 'Marron'],
            'light-brown'   => ['code' => 'light-brown', 'label' => 'Marron clair'],
            'dark-brown'    => ['code' => 'dark-brown', 'label' => 'Marron foncé'],
            'green'         => ['code' => 'green', 'label' => 'Vert'],
            'dark-green'    => ['code' => 'dark-green', 'label' => 'Vert foncé'],
            'purple'        => ['code' => 'purple', 'label' => 'Violet'],
            'light-purple'  => ['code' => 'light-purple', 'label' => 'Violet clair'],
            'pink'          => ['code' => 'pink', 'label' => 'Rose'],
            'bordeaux'      => ['code' => 'bordeaux', 'label' => 'Bordeaux'],
            'cyan'          => ['code' => 'cyan', 'label' => 'Cyan'],
            'azure'         => ['code' => 'azure', 'label' => 'Azure'],
            'grey-azure'    => ['code' => 'grey-azure', 'label' => 'Grey Azure'],
            'weighted-teal' => ['code' => 'weighted-teal', 'label' => 'Weighted Teal'],
            'sage-green'    => ['code' => 'sage-green', 'label' => 'Sage Green'],
            'yellow'        => ['code' => 'yellow', 'label' => 'Jaune'],
            'wood'          => ['code' => 'wood', 'label' => 'Bois'],
        ];

        $colorImagesHommeSlices = [
            'Pantalon Jogging'          => ['black' => [0,1,2],   'blue'   => [3,4,5], 'grey'         => [6,7,8],],
            'Pantalon Training'         => ['blue'  => [0,1,2],   'black'  => [3,4,5],],
            'Short Homme Confort'       => ['black' => [0,1,2],   'green'  => [3,4,5], 'purple'       => [6,7,8],],
            'Short Homme Training'      => ['black' => [0,1,2],   'grey'   => [3,4,5], 'dark-green'   => [6,7,8],],
            'Sweat Homme Oversize'      => ['black' => [0,1,2],   'grey'   => [3,4,5], 'navy'         => [6,7,8],],
            'Sweat Homme Zippé'         => ['black' => [0,1,2],   'grey'   => [3,4,5], 'brown'        => [6,7,8],],
            'T-shirt Oversize Homme'    => ['black' => [0,1,2],   'white'  => [3,4,5], 'grey-azure'   => [6,7,8],],
            'T-shirt Training Homme'    => ['black' => [0,1,2],   'navy'   => [3,4,5], 'white'        => [6,7,8],],
            'Veste Coupe-Vent Homme'    => ['green' => [0,1],     'blue'   => [2],     'black'        => [3],],
            'Veste Training Homme'      => ['black' => [0,1,2,3], 'grey' => [4,5,6,7],],
        ];

        $colorImagesFemmeSlices = [
            'Bandeau Training'          => ['navy'  => [0],     'bordeaux' => [1], 'black' => [2], 'pink' => [3], 'green' => [4]],
            'Gourde Fitness'            => ['black' => [0,1],   'weighted-teal' => [2,3]],
            'Sac de sport Femme'        => ['black' => [0,1,2], 'pink' => [3,4,5], 'grey' => [6,7,8]],
            'Brassière Confort'         => ['black' => [0,1,2], 'white' => [3,4,5], 'azure' => [6,7,8]],
            'Brassière Impact'          => ['black' => [0,1,2], 'white' => [3,4,5]],
            'Jogging Comfort'           => ['white' => [0,1,2], 'bordeaux'  => [3,4,5], 'grey' => [6,7,8], 'blue'   => [9,10,11]],
            'Jogging Performance'       => ['black' => [0,1,2, 3,], 'brown' => [4, 5,6,7]],
            'Jogging Training'          => ['black' => [0,1,2, 3], 'grey'  => [4, 5, 6, 7], 'cyan' => [8, 9, 10, 11], 'bordeaux' => [12, 13, 14, 15]],
            'Legging Performance'       => ['black' => [0,1,2], 'blue' => [3,4,5], 'azure' => [6,7,8]],
            'Legging Sculpt'            => ['light-brown' => [0,1,2], 'black'  => [3,4,5], 'cyan' => [6,7], 'light-purple' => [8,9], 'blue' => [10,11]],
            'Legging Seamless'          => ['blue'  => [0,1], 'light-brown' => [2,3,4], 'black'  => [5,6], 'green' => [7,8, 9], 'pink' => [10,11,12], 'dark-brown' => [13,14]],
            'Short Femme Seamless'      => ['blue'  => [0,1,2], 'black' => [3,4,5], 'green' => [6,7,8]],
            'Short Femme Training'      => ['black'  => [0,1,2], 'blue' => [3,4,5], 'pink' => [6,7,8]],
            'Sweat Femme Oversize'      => ['brown' => [0,1,2,3], 'grey'  => [4,5,6,7], 'black' => [8,9,10,11]],
            'Sweat Femme Zippé'         => ['grey'  => [0,1,2], 'blue' => [3,4,5], 'white' => [6,7,8]],
            'T-shirt Oversize Femme'    => ['black' => [0,1,2], 'white'  => [3,4,5], 'grey' => [6,7,8]],
            'T-shirt Training Femme'    => ['black' => [0,1,2], 'white'  => [3,4,5]],
            'Veste Coupe-Vent Femme'    => ['yellow' => [0,1,2], 'black'  => [3,4,5]],
            'Veste Training Femme'      => ['blue' => [0,1], 'dark-brown'  => [2,3], 'light-brown' => [4,5]],
        ];

        $colorImagesEquipments = [
            'Gym Ball'     => ['blue' => [0,1,2], 'purple'  => [3,4,5]],
            'Tapis de sol' => ['purple' => [0,1,2], 'sage-green'  => [3,4,5]],
        ];

        // merge
        $colorSlicesByProductName = array_replace($colorImagesHommeSlices, $colorImagesFemmeSlices, $colorImagesEquipments);

        // --- Mapping "Nom du produit" => [couleurs ...] ---
        $colorsByProductName = [
            // Equipments
            'Barre Olympique 20kg'          => [$c['grey']],
            'Barre Olympique 15kg'          => [$c['grey']],
            'Barre Curl'                    => [$c['grey']],
            'Anneaux Gym'                   => [$c['wood']],
            'Parallettes'                   => [$c['wood']],
            'Barre de traction murale'      => [$c['black']],
            'Gym Ball'                      => [$c['blue'], $c['purple']],
            'Rouleau de massage'            => [$c['black']],
            'Tapis de sol'                  => [$c['purple'], $c['sage-green']],
            'Banc de musculation réglable'  => [$c['black']],
            'Disques'                       => [$c['black']],
            'Hack Squat Pro'                => [$c['black']],
            'Presse à jambes'               => [$c['black']],
            'Air Bike'                      => [$c['black']],
            'Rameur Indoor'                 => [$c['black']],

            // Hommes
            'Ceinture de musculation'       => [$c['black']],
            'Gants Training'                => [$c['black']],
            'Sac de sport Homme'            => [$c['black']],
            'Pantalon Jogging'              => [$c['black'], $c['blue'], $c['grey']],
            'Pantalon Training'             => [$c['blue'], $c['black']],
            'Short Homme Confort'           => [$c['black'], $c['green'], $c['purple']],
            'Short Homme Training'          => [$c['black'], $c['grey'], $c['dark-green']],
            'Sweat Homme Oversize'          => [$c['black'], $c['grey'], $c['navy']],
            'Sweat Homme Zippé'             => [$c['black'], $c['grey'], $c['brown']],
            'T-shirt Oversize Homme'        => [$c['black'], $c['white'], $c['grey-azure']],
            'T-shirt Training Homme'        => [$c['black'], $c['navy'], $c['white']],
            'Veste Coupe-Vent Homme'        => [$c['green'], $c['blue'], $c['black']],
            'Veste Training Homme'          => [$c['black'], $c['grey']],

            // Femmes
            'Bandeau Training' => [$c['navy'], $c['bordeaux'], $c['black'], $c['pink'], $c['green']],
            'Gourde Fitness' => [$c['black'], $c['weighted-teal']],
            'Sac de sport Femme' => [$c['black'], $c['pink'], $c['grey']],
            'Brassière Confort' => [$c['black'], $c['white'], $c['azure']],
            'Brassière Impact' => [$c['black'], $c['white']],
            'Jogging Comfort' => [$c['white'], $c['bordeaux'], $c['grey'], $c['blue']],
            'Jogging Performance' => [$c['black'], $c['brown']],
            'Jogging Training' => [$c['black'], $c['grey'], $c['cyan'], $c['bordeaux']],
            'Legging Performance' => [$c['black'], $c['blue'], $c['azure']],
            'Legging Sculpt' => [$c['light-brown'], $c['black'], $c['cyan'], $c['light-purple'], $c['blue']],
            'Legging Seamless' => [$c['blue'], $c['light-brown'], $c['black'], $c['green'], $c['pink'], $c['dark-brown']],
            'Short Femme Seamless' => [$c['blue'], $c['black'], $c['green']],
            'Short Femme Training' => [$c['black'], $c['blue'], $c['pink']],
            'Sweat Femme Oversize' => [$c['brown'], $c['grey'], $c['black']],
            'Sweat Femme Zippé' => [$c['grey'], $c['blue'], $c['white']],
            'T-shirt Oversize Femme' => [$c['black'], $c['white'], $c['grey']],
            'T-shirt Training Femme' => [$c['black'], $c['white']],
            'Veste Coupe-Vent Femme' => [$c['yellow'], $c['black']],
            'Veste Training Femme' => [$c['blue'], $c['dark-brown'], $c['light-brown']],
        ];

        // --- Mapping produits avec goûts (nutrition) ---
        $flavorsByProductName = [
            'Whey Pure Professionnal 500g' => [
                ['code' => 'white-coconut', 'label' => 'White Coconut'],
                ['code' => 'coconut-lime', 'label' => 'Coconut & Lime'],
                ['code' => 'intense-chocolate', 'label' => 'Intense Chocolate'],
            ],
            'Whey Pure Professionnal 900g' => [
                ['code' => 'stracciatella', 'label' => 'Stracciatella'],
                ['code' => 'cookies-cream', 'label' => 'Cookies & Cream'],
                ['code' => 'cuor-di-cioccolato-bianco', 'label' => 'Cuor di Cioccolato Bianco'],
            ],
            'Whey Pure Professionnal 2 kg' => [
                ['code' => 'cookies-cream', 'label' => 'Cookies & Cream'],
                ['code' => 'white-chocolate-forest-fruits', 'label' => 'White Chocolate + Forest Fruits'],
            ],
            'Isolate Pure Professionnal 500g' => [
                ['code' => 'wafer-nocciola', 'label' => 'Wafer Nocciola'],
                ['code' => 'white-chocolate-dark-cookies', 'label' => 'White Chocolate + Dark Cookies'],
                ['code' => 'caramel-hazelnut', 'label' => 'Caramel Hazelnut'],
            ],
            'Isolate Pure Professionnal 900g' => [
                ['code' => 'chocolate-dark-cookies', 'label' => 'Chocolate + Dark Cookies'],
            ],
            'Isolate Pure Professionnal 2 kg' => [
                ['code' => 'dark-cookies', 'label' => 'Dark Cookies'],
                ['code' => 'white-chocolate-dark-cookies', 'label' => 'White Chocolate + Dark Cookies'],
                ['code' => 'chocobounty', 'label' => 'Chocobounty'],
            ],
            'Hydro Purebar 55g' => [
                ['code' => 'white-chocolate', 'label' => 'White Chocolate'],
                ['code' => 'chocolate-banana', 'label' => 'Chocolate Banana'],
                ['code' => 'chocolate-coconut', 'label' => 'Chocolate Coconut'],
            ],
            'Isolate Purebar 50g' => [
                ['code' => 'dark-cookies', 'label' => 'Dark Cookies'],
                ['code' => 'intense-chocolate', 'label' => 'Intense Chocolate'],
                ['code' => 'wafer-nocciola', 'label' => 'Wafer Nocciola'],
            ],
            // Créatines (1 seul goût => pas de clones)
            'Creatine Micro Pure Zero Carb 250g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
            'Creatine Micro Pure Zero Carb 500g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
            'Creaclon Micro Pure Pro 250g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
            'Creaclon Micro Pure Pro 500g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
        ];

        $flavorNames = array_keys($flavorsByProductName);

        // 1) GROUPES GOÛTS (nutrition) d'abord
        foreach ($flavorsByProductName as $productName => $flavors) {
            $base = Product::where('name', $productName)->first();
            if (!$base) continue;

            $group = ProductGroup::updateOrCreate(
                ['slug' => Str::slug($productName)],
                ['name' => $productName, 'type' => 'flavor']
            );

            // Images existantes du produit (déjà seedées par ProductSeeder)
            $baseUrls = DB::table('product_images')
                ->where('product_id', $base->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('url')
                ->values()
                ->toArray();

            // Assign 1er goût au produit existant
            DB::table('products')->where('id', $base->id)->update([
                'group_id'    => $group->id,
                'color_code'  => $flavors[0]['code'],
                'color_label' => $flavors[0]['label'],
            ]);

            // Mettre l'image 0 sur le base (main+hover)
            $main0 = $baseUrls[0] ?? null;
            if ($main0) {
                $this->replaceProductImages((int)$base->id, [$main0, $main0]);
            }

            // Cloner autres goûts
            foreach (array_slice($flavors, 1) as $idx => $flavor) {
                $i = $idx + 1; // index image
                $newId = $this->cloneProductVariant((int)$base->id, (int)$group->id, $flavor['code'], $flavor['label']);

                $main = $baseUrls[$i] ?? $baseUrls[0] ?? null;
                if ($newId && $main) {
                    $this->replaceProductImages((int)$newId, [$main, $main]);
                }
            }
        }

        // 2) GROUPES COULEURS (hors nutrition)
        foreach ($colorsByProductName as $productName => $colors) {
            if (in_array($productName, $flavorNames, true)) {
                continue;
            }

            $base = Product::where('name', $productName)->first();
            if (!$base) continue;

            $group = ProductGroup::firstOrCreate(
                ['slug' => Str::slug($productName)],
                ['name' => $productName, 'type' => 'color']
            );

            $baseUrls = DB::table('product_images')
                ->where('product_id', $base->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('url')
                ->values()
                ->toArray();

            $slicesByCode = $colorSlicesByProductName[$productName] ?? null;
            $canSlice = $this->hasSlicesForAllVariants($slicesByCode, $colors);

            // Assign 1ère couleur au produit existant
            DB::table('products')->where('id', $base->id)->update([
                'group_id' => $group->id,
                'color_code' => $colors[0]['code'],
                'color_label' => $colors[0]['label'],
            ]);

            if ($canSlice) {
                // Base = slice de la 1ère couleur
                $firstCode = $colors[0]['code'];
                $urlsBase = $this->sliceUrls($baseUrls, $slicesByCode[$firstCode] ?? []);
                if (!empty($urlsBase)) {
                    $this->replaceProductImages((int)$base->id, $urlsBase);
                }

                // Clones = slices correspondantes
                foreach (array_slice($colors, 1) as $color) {
                    $newId = $this->cloneProductVariant((int)$base->id, (int)$group->id, $color['code'], $color['label']);
                    if (!$newId) continue;

                    $urls = $this->sliceUrls($baseUrls, $slicesByCode[$color['code']] ?? []);
                    if (!empty($urls)) {
                        $this->replaceProductImages((int)$newId, $urls);
                    } else {
                        // sécurité
                        $this->copyImagesFromBase((int)$base->id, (int)$newId);
                    }
                }
            } else {
                foreach (array_slice($colors, 1) as $color) {
                    $newId = $this->cloneProductVariant((int)$base->id, (int)$group->id, $color['code'], $color['label']);
                    if ($newId) {
                        $this->copyImagesFromBase((int)$base->id, (int)$newId);
                    }
                }
            }
        }

        // 3) Fallback : tout ce qui n’est pas groupé
        $handled = array_unique(array_merge(array_keys($colorsByProductName), array_keys($flavorsByProductName)));

        $remaining = Product::whereNotIn('name', $handled)->get();
        foreach ($remaining as $product) {
            $group = ProductGroup::firstOrCreate(
                ['slug' => Str::slug($product->name)],
                ['name' => $product->name, 'type' => null]
            );

            DB::table('products')->where('id', $product->id)->update([
                'group_id'    => $group->id,
                'color_code'  => null,
                'color_label' => null,
            ]);
        }
    }

    // Vrai si mapping slice complet
    private function hasSlicesForAllVariants(?array $slicesByCode, array $colors): bool
    {
        if (!$slicesByCode || !is_array($slicesByCode)) return false;

        foreach ($colors as $c) {
            $code = $c['code'] ?? null;
            if (!$code || !array_key_exists($code, $slicesByCode)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Extrait des URLs depuis une liste d'indexes.
     */
    private function sliceUrls(array $baseUrls, array $indexes): array
    {
        $out = [];
        foreach ($indexes as $idx) {
            if (isset($baseUrls[$idx])) {
                $out[] = $baseUrls[$idx];
            }
        }
        // unique + ordre conservé
        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Clone un produit (variant = couleur ou goût)
     */
    private function cloneProductVariant(int $baseProductId, int $groupId, string $variantCode, string $variantLabel): int
    {
        $base = DB::table('products')->where('id', $baseProductId)->first();
        if (!$base) return 0;

        $now  = now();
        $slug = $this->makeSlug((string)$base->name, $variantCode);
        $sku  = $this->makeSku((string)$base->name, $variantCode);

        $newId = DB::table('products')->insertGetId([
            'supplier_id' => $base->supplier_id,
            'group_id'    => $groupId,
            'name'        => $base->name,
            'slug'        => $slug,
            'brand'       => $base->brand,
            'origin'      => $base->origin,
            'color_code'  => $variantCode,
            'color_label' => $variantLabel,
            'description' => $base->description,
            'price_ht'    => $base->price_ht,
            'price_ttc'   => $base->price_ttc,
            'vat'         => $base->vat,
            'sku'         => $sku,
            'barcode'     => $base->barcode,
            'weight'      => $base->weight,
            'attributes'  => $base->attributes,
            'is_active'   => $base->is_active,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // Copier categories
        $cats = DB::table('product_category')->where('product_id', $baseProductId)->pluck('category_id');
        foreach ($cats as $catId) {
            DB::table('product_category')->insert([
                'product_id' => $newId,
                'category_id'=> $catId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return (int)$newId;
    }

    /**
     * Remplace complètement les images d'un produit.
     */
    private function replaceProductImages(int $productId, array $urls): void
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if (count($urls) === 0) return;

        // si on n’a qu’une image, on la duplique pour hover
        if (count($urls) === 1) $urls[] = $urls[0];

        $now = now();

        DB::table('product_images')->where('product_id', $productId)->delete();

        foreach ($urls as $i => $url) {
            DB::table('product_images')->insert([
                'product_id' => $productId,
                'url'        => $url,
                'is_main'    => $i === 0,
                'position'   => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Copie toutes les images du produit de base vers le clone.
     */
    private function copyImagesFromBase(int $baseProductId, int $newProductId): void
    {
        $images = DB::table('product_images')
            ->where('product_id', $baseProductId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($images->isEmpty()) return;

        $now = now();

        foreach ($images as $img) {
            DB::table('product_images')->insert([
                'product_id' => $newProductId,
                'url'        => $img->url,
                'is_main'    => (bool)$img->is_main,
                'position'   => (int)$img->position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Génère un SKU garanti <= longueur colonne products.sku
     */
    private function makeSku(string $name, string $variantCode): string
    {
        $max = (int)($this->skuMax ?? 80);

        // suffixe "-1234" = 5 chars
        $suffix = '-' . rand(1000, 9999);
        $keep   = max(1, $max - strlen($suffix));
        $base = strtoupper(Str::slug($name . '-' . $variantCode));
        $base = substr($base, 0, $keep);
        return $base . $suffix;
    }

    /**
     * Génère un slug garanti <= longueur colonne products.slug
     */
    private function makeSlug(string $name, string $variantCode): string
    {
        $max = (int)($this->slugMax ?? 255);
        $suffix = '-' . rand(100, 999);
        $raw    = Str::slug($name . '-' . $variantCode);
        $keep   = max(1, $max - strlen($suffix));
        return substr($raw, 0, $keep) . $suffix;
    }

    /**
     * Lit la longueur réelle d'une colonne VARCHAR via information_schema.
     */
    private function getColumnMaxLen(string $table, string $column, int $default): int
    {
        try {
            $row = DB::selectOne(
                "SELECT CHARACTER_MAXIMUM_LENGTH AS len
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1",
                [$table, $column]
            );

            $len = isset($row->len) ? (int)$row->len : 0;
            return $len > 0 ? $len : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function disableFk(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } catch (Throwable $exception) {
            // noop
        }
    }

    private function enableFk(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (Throwable $exception) {
            // noop
        }
    }
}
