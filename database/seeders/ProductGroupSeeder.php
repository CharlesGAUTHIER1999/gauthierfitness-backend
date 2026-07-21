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
    private ?int $sku_max = null;

    private ?int $slug_max = null;

    /** Group products by color/flavor variants, cloning base products into variant rows and assigning them to product groups with their images. */
    public function run(): void
    {
        $this->disableFk();

        DB::table('products')->update([
            'group_id' => null,
            'color_code' => null,
            'color_label' => null,
        ]);

        DB::table('product_groups')->truncate();

        $this->enableFk();

        $this->sku_max = $this->getColumnMaxLen('products', 'sku', 80);
        $this->slug_max = $this->getColumnMaxLen('products', 'slug', 255);

        $c = [
            'black' => ['code' => 'black', 'label' => 'Noir'],
            'white' => ['code' => 'white', 'label' => 'Blanc'],
            'grey' => ['code' => 'grey', 'label' => 'Gris'],
            'blue' => ['code' => 'blue', 'label' => 'Bleu'],
            'green' => ['code' => 'green', 'label' => 'Vert'],
            'purple' => ['code' => 'purple', 'label' => 'Violet'],
            'cyan' => ['code' => 'cyan', 'label' => 'Cyan'],
            'sage-green' => ['code' => 'sage-green', 'label' => 'Sage Green'],
            'red' => ['code' => 'red', 'label' => 'Rouge'],
            'wood' => ['code' => 'wood', 'label' => 'Bois'],
        ];

        $color_slices_by_product_key = [
            'femmes-pantalons|Pantalon Classic' => [
                'grey' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'blue' => [8, 9, 10, 11],
            ],
            'hommes-pantalons|Pantalon Classic' => [
                'grey' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'blue' => [8, 9, 10, 11],
            ],

            'femmes-pantalons|Pantalon Training' => [
                'white' => [0, 1, 2],
                'black' => [3, 4, 5],
                'blue' => [6, 7, 8],
            ],
            'hommes-pantalons|Pantalon Training' => [
                'white' => [0, 1, 2],
                'black' => [3, 4, 5],
                'blue' => [6, 7, 8],
            ],

            'femmes-sweats|Sweat Classic' => [
                'white' => [0, 1, 2],
                'black' => [3, 4, 5],
                'grey' => [6, 7, 8],
            ],
            'hommes-sweats|Sweat Classic' => [
                'white' => [0, 1, 2],
                'black' => [3, 4, 5],
                'grey' => [6, 7, 8],
            ],

            'femmes-sweats|Sweat Zippe' => [
                'white' => [0, 1, 2],
                'black' => [3, 4, 5],
                'red' => [6, 7, 8],
            ],
            'hommes-sweats|Sweat Zippe' => [
                'white' => [0, 1, 2],
                'black' => [3, 4, 5],
                'red' => [6, 7, 8],
            ],

            'femmes-tshirts|T-shirt Oversize' => [
                'white' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'grey' => [8, 9, 10, 11],
            ],
            'hommes-tshirts|T-shirt Oversize' => [
                'white' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'grey' => [8, 9, 10, 11],
            ],

            'femmes-tshirts|T-shirt Training' => [
                'white' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'grey' => [8, 9, 10, 11],
            ],
            'hommes-tshirts|T-shirt Training' => [
                'white' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'grey' => [8, 9, 10, 11],
            ],

            'femmes-vestes|Veste Classic' => [
                'white' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'red' => [8, 9, 10, 11],
            ],
            'hommes-vestes|Veste Classic' => [
                'white' => [0, 1, 2, 3],
                'black' => [4, 5, 6, 7],
                'red' => [8, 9, 10, 11],
            ],

            'femmes-vestes|Veste Coupe-Vent' => [
                'green' => [0, 1, 2],
                'blue' => [3, 4, 5],
                'cyan' => [6, 7, 8],
            ],
            'hommes-vestes|Veste Coupe-Vent' => [
                'green' => [0, 1, 2],
                'blue' => [3, 4, 5],
                'cyan' => [6, 7, 8],
            ],

            'equipments-mobilite|Gym Ball' => [
                'blue' => [0, 1, 2],
                'purple' => [3, 4, 5],
            ],
            'equipments-mobilite|Tapis de sol' => [
                'purple' => [0, 1, 2],
                'sage-green' => [3, 4, 5],
            ],
        ];

        $colors_by_product_key = [
            'equipments-barres|Barre Olympique 20kg' => [$c['grey']],
            'equipments-barres|Barre Olympique 15kg' => [$c['grey']],
            'equipments-barres|Barre Curl' => [$c['grey']],
            'equipments-calisthenie|Anneaux Gym' => [$c['wood']],
            'equipments-calisthenie|Parallettes' => [$c['wood']],
            'equipments-calisthenie|Barre de traction murale' => [$c['black']],
            'equipments-mobilite|Gym Ball' => [$c['blue'], $c['purple']],
            'equipments-mobilite|Rouleau de massage' => [$c['black']],
            'equipments-mobilite|Tapis de sol' => [$c['purple'], $c['sage-green']],
            'equipments-musculation|Banc de musculation réglable' => [$c['black']],
            'equipments-musculation|Disques' => [$c['black']],
            'equipments-musculation|Hack Squat Pro' => [$c['black']],
            'equipments-musculation|Presse à jambes' => [$c['black']],
            'equipments-prepa|Air Bike' => [$c['black']],
            'equipments-prepa|Rameur Indoor' => [$c['black']],
            'femmes-pantalons|Pantalon Classic' => [$c['grey'], $c['black'], $c['blue']],
            'hommes-pantalons|Pantalon Classic' => [$c['grey'], $c['black'], $c['blue']],
            'femmes-pantalons|Pantalon Training' => [$c['white'], $c['black'], $c['blue']],
            'hommes-pantalons|Pantalon Training' => [$c['white'], $c['black'], $c['blue']],
            'femmes-sweats|Sweat Classic' => [$c['white'], $c['black'], $c['grey']],
            'hommes-sweats|Sweat Classic' => [$c['white'], $c['black'], $c['grey']],
            'femmes-sweats|Sweat Zippe' => [$c['white'], $c['black'], $c['red']],
            'hommes-sweats|Sweat Zippe' => [$c['white'], $c['black'], $c['red']],
            'femmes-tshirts|T-shirt Oversize' => [$c['white'], $c['black'], $c['grey']],
            'hommes-tshirts|T-shirt Oversize' => [$c['white'], $c['black'], $c['grey']],
            'femmes-tshirts|T-shirt Training' => [$c['white'], $c['black'], $c['grey']],
            'hommes-tshirts|T-shirt Training' => [$c['white'], $c['black'], $c['grey']],
            'femmes-vestes|Veste Classic' => [$c['white'], $c['black'], $c['red']],
            'hommes-vestes|Veste Classic' => [$c['white'], $c['black'], $c['red']],
            'femmes-vestes|Veste Coupe-Vent' => [$c['green'], $c['blue'], $c['cyan']],
            'hommes-vestes|Veste Coupe-Vent' => [$c['green'], $c['blue'], $c['cyan']],
        ];

        $flavors_by_product_key = [
            'nutrition-proteines-poudre|Whey Pure Professionnal 500g' => [
                ['code' => 'white-coconut', 'label' => 'White Coconut'],
                ['code' => 'coconut-lime', 'label' => 'Coconut & Lime'],
                ['code' => 'intense-chocolate', 'label' => 'Intense Chocolate'],
            ],
            'nutrition-proteines-poudre|Whey Pure Professionnal 900g' => [
                ['code' => 'stracciatella', 'label' => 'Stracciatella'],
                ['code' => 'cookies-cream', 'label' => 'Cookies & Cream'],
                ['code' => 'cuor-di-cioccolato-bianco', 'label' => 'Cuor di Cioccolato Bianco'],
            ],
            'nutrition-proteines-poudre|Whey Pure Professionnal 2 kg' => [
                ['code' => 'cookies-cream', 'label' => 'Cookies & Cream'],
                ['code' => 'white-chocolate-forest-fruits', 'label' => 'White Chocolate + Forest Fruits'],
            ],
            'nutrition-isolats|Isolate Pure Professionnal 500g' => [
                ['code' => 'wafer-nocciola', 'label' => 'Wafer Nocciola'],
                ['code' => 'white-chocolate-dark-cookies', 'label' => 'White Chocolate + Dark Cookies'],
                ['code' => 'caramel-hazelnut', 'label' => 'Caramel Hazelnut'],
            ],
            'nutrition-isolats|Isolate Pure Professionnal 900g' => [
                ['code' => 'chocolate-dark-cookies', 'label' => 'Chocolate + Dark Cookies'],
            ],
            'nutrition-isolats|Isolate Pure Professionnal 2 kg' => [
                ['code' => 'dark-cookies', 'label' => 'Dark Cookies'],
                ['code' => 'white-chocolate-dark-cookies', 'label' => 'White Chocolate + Dark Cookies'],
                ['code' => 'chocobounty', 'label' => 'Chocobounty'],
            ],
            'nutrition-barres|Hydro Purebar 55g' => [
                ['code' => 'white-chocolate', 'label' => 'White Chocolate'],
                ['code' => 'chocolate-banana', 'label' => 'Chocolate Banana'],
                ['code' => 'chocolate-coconut', 'label' => 'Chocolate Coconut'],
            ],
            'nutrition-barres|Isolate Purebar 50g' => [
                ['code' => 'dark-cookies', 'label' => 'Dark Cookies'],
                ['code' => 'intense-chocolate', 'label' => 'Intense Chocolate'],
                ['code' => 'wafer-nocciola', 'label' => 'Wafer Nocciola'],
            ],
            'nutrition-creatine|Creatine Micro Pure Zero Carb 250g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
            'nutrition-creatine|Creatine Micro Pure Zero Carb 500g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
            'nutrition-creatine|Creaclon Micro Pure Pro 250g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
            'nutrition-creatine|Creaclon Micro Pure Pro 500g' => [
                ['code' => 'unflavoured', 'label' => 'Unflavoured'],
            ],
        ];

        $flavor_keys = array_keys($flavors_by_product_key);

        foreach ($flavors_by_product_key as $product_key => $flavors) {
            [$category_slug, $product_name] = explode('|', $product_key, 2);

            $base = $this->findProductByCategoryAndName($category_slug, $product_name);
            if (! $base) {
                continue;
            }

            $group = ProductGroup::updateOrCreate(
                ['slug' => Str::slug($category_slug.'-'.$product_name)],
                ['name' => $product_name, 'type' => 'flavor']
            );

            $base_urls = DB::table('product_images')
                ->where('product_id', $base->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('url')
                ->values()
                ->toArray();

            DB::table('products')->where('id', $base->id)->update([
                'group_id' => $group->id,
                'color_code' => $flavors[0]['code'],
                'color_label' => $flavors[0]['label'],
            ]);

            $main0 = $base_urls[0] ?? null;
            if ($main0) {
                $this->replaceProductImages((int) $base->id, [$main0, $main0]);
            }

            foreach (array_slice($flavors, 1) as $idx => $flavor) {
                $i = $idx + 1;
                $new_id = $this->cloneProductVariant((int) $base->id, (int) $group->id, $flavor['code'], $flavor['label']);

                $main = $base_urls[$i] ?? $base_urls[0] ?? null;
                if ($new_id && $main) {
                    $this->replaceProductImages((int) $new_id, [$main, $main]);
                }
            }
        }

        foreach ($colors_by_product_key as $product_key => $colors) {
            if (in_array($product_key, $flavor_keys, true)) {
                continue;
            }

            [$category_slug, $product_name] = explode('|', $product_key, 2);

            $base = $this->findProductByCategoryAndName($category_slug, $product_name);
            if (! $base) {
                continue;
            }

            $group = ProductGroup::firstOrCreate(
                ['slug' => Str::slug($category_slug.'-'.$product_name)],
                ['name' => $product_name, 'type' => 'color']
            );

            $base_urls = DB::table('product_images')
                ->where('product_id', $base->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('url')
                ->values()
                ->toArray();

            $slices_by_code = $color_slices_by_product_key[$product_key] ?? null;
            $can_slice = $this->hasSlicesForAllVariants($slices_by_code, $colors);

            DB::table('products')->where('id', $base->id)->update([
                'group_id' => $group->id,
                'color_code' => $colors[0]['code'],
                'color_label' => $colors[0]['label'],
            ]);

            if ($can_slice) {
                $first_code = $colors[0]['code'];
                $urls_base = $this->sliceUrls($base_urls, $slices_by_code[$first_code] ?? []);

                if (! empty($urls_base)) {
                    $this->replaceProductImages((int) $base->id, $urls_base);
                }

                foreach (array_slice($colors, 1) as $color) {
                    $new_id = $this->cloneProductVariant((int) $base->id, (int) $group->id, $color['code'], $color['label']);
                    if (! $new_id) {
                        continue;
                    }

                    $urls = $this->sliceUrls($base_urls, $slices_by_code[$color['code']] ?? []);
                    if (! empty($urls)) {
                        $this->replaceProductImages((int) $new_id, $urls);
                    } else {
                        $this->copyImagesFromBase((int) $base->id, (int) $new_id);
                    }
                }
            } else {
                foreach (array_slice($colors, 1) as $color) {
                    $new_id = $this->cloneProductVariant((int) $base->id, (int) $group->id, $color['code'], $color['label']);
                    if ($new_id) {
                        $this->copyImagesFromBase((int) $base->id, (int) $new_id);
                    }
                }
            }
        }

        $handled_keys = array_unique(array_merge(array_keys($colors_by_product_key), array_keys($flavors_by_product_key)));

        $all_products = Product::with('categories')->get();

        foreach ($all_products as $product) {
            $category_slug = $this->firstCategorySlug((int) $product->id) ?? 'default';
            $product_key = $category_slug.'|'.$product->name;

            if (in_array($product_key, $handled_keys, true)) {
                continue;
            }

            $group = ProductGroup::firstOrCreate(
                ['slug' => Str::slug($category_slug.'-'.$product->name)],
                ['name' => $product->name, 'type' => null]
            );

            DB::table('products')->where('id', $product->id)->update([
                'group_id' => $group->id,
                'color_code' => null,
                'color_label' => null,
            ]);
        }
    }

    /** Find a product by exact name that belongs to the given category slug. */
    private function findProductByCategoryAndName(string $category_slug, string $product_name): ?Product
    {
        return Product::where('name', $product_name)
            ->whereHas('categories', function ($q) use ($category_slug) {
                $q->where('slug', $category_slug);
            })
            ->first();
    }

    /** Check whether every color variant has a defined image-index slice. */
    private function hasSlicesForAllVariants(?array $slices_by_code, array $colors): bool
    {
        if (! $slices_by_code || ! is_array($slices_by_code)) {
            return false;
        }

        foreach ($colors as $c) {
            $code = $c['code'] ?? null;
            if (! $code || ! array_key_exists($code, $slices_by_code)) {
                return false;
            }
        }

        return true;
    }

    /** Pick a subset of image URLs from the base list using the given indexes. */
    private function sliceUrls(array $base_urls, array $indexes): array
    {
        $out = [];

        foreach ($indexes as $idx) {
            if (isset($base_urls[$idx])) {
                $out[] = $base_urls[$idx];
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** Clone a base product row into a new variant (same data, different color/flavor, slug and SKU). */
    private function cloneProductVariant(int $base_product_id, int $group_id, string $variant_code, string $variant_label): int
    {
        $base = DB::table('products')->where('id', $base_product_id)->first();
        if (! $base) {
            return 0;
        }

        $now = now();
        $slug = $this->makeSlug((string) $base->slug, $variant_code);
        $sku = $this->makeSku((string) $base->sku, $variant_code);

        $new_id = DB::table('products')->insertGetId([
            'supplier_id' => $base->supplier_id,
            'group_id' => $group_id,
            'name' => $base->name,
            'slug' => $slug,
            'brand' => $base->brand,
            'origin' => $base->origin,
            'color_code' => $variant_code,
            'color_label' => $variant_label,
            'description' => $base->description,
            'price_ht' => $base->price_ht,
            'price_ttc' => $base->price_ttc,
            'vat' => $base->vat,
            'sku' => $sku,
            'barcode' => $base->barcode,
            'weight' => $base->weight,
            'attributes' => $base->attributes,
            'is_active' => $base->is_active,
            'is_customizable' => $base->is_customizable ?? false,
            'customization_mode' => $base->customization_mode,
            'allow_text_customization' => $base->allow_text_customization ?? false,
            'allow_image_upload' => $base->allow_image_upload ?? false,
            'allow_ai_generation' => $base->allow_ai_generation ?? false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cats = DB::table('product_category')
            ->where('product_id', $base_product_id)
            ->pluck('category_id');

        foreach ($cats as $cat_id) {
            DB::table('product_category')->insert([
                'product_id' => $new_id,
                'category_id' => $cat_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return (int) $new_id;
    }

    /** Delete a product's existing images and insert the given URLs as its new gallery. */
    private function replaceProductImages(int $product_id, array $urls): void
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if (count($urls) === 0) {
            return;
        }

        if (count($urls) === 1) {
            $urls[] = $urls[0];
        }

        $now = now();

        DB::table('product_images')->where('product_id', $product_id)->delete();

        foreach ($urls as $i => $url) {
            DB::table('product_images')->insert([
                'product_id' => $product_id,
                'url' => $url,
                'is_main' => $i === 0,
                'position' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Copy all images from a base product to a newly cloned variant product. */
    private function copyImagesFromBase(int $base_product_id, int $new_product_id): void
    {
        $images = DB::table('product_images')
            ->where('product_id', $base_product_id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($images->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($images as $img) {
            DB::table('product_images')->insert([
                'product_id' => $new_product_id,
                'url' => $img->url,
                'is_main' => (bool) $img->is_main,
                'position' => (int) $img->position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Build a unique SKU for a variant by truncating the base SKU and appending variant + random suffix. */
    private function makeSku(string $base_sku, string $variant_code): string
    {
        $max = (int) ($this->sku_max ?? 80);

        // Keep part of the base SKU + variant + random suffix to guarantee uniqueness
        $variant = strtoupper(Str::slug($variant_code));
        $random = '-'.rand(1000, 9999);

        // Reserve room for "-VARIANT-1234"
        $suffix = '-'.$variant.$random;
        $keep = max(1, $max - strlen($suffix));

        return substr($base_sku, 0, $keep).$suffix;
    }

    /** Build a unique slug for a variant by truncating the base slug and appending the variant code. */
    private function makeSlug(string $base_slug, string $variant_code): string
    {
        $max = (int) ($this->slug_max ?? 255);
        $suffix = '-'.Str::slug($variant_code);
        $keep = max(1, $max - strlen($suffix));

        return substr($base_slug, 0, $keep).$suffix;
    }

    /** Look up a column's max character length from information_schema, falling back to a default. */
    private function getColumnMaxLen(string $table, string $column, int $default): int
    {
        try {
            $row = DB::selectOne(
                'SELECT CHARACTER_MAXIMUM_LENGTH AS len
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1',
                [$table, $column]
            );

            $len = isset($row->len) ? (int) $row->len : 0;

            return $len > 0 ? $len : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    /** Get the slug of a product's first assigned category (ordered by parent then id). */
    private function firstCategorySlug(int $product_id): ?string
    {
        return DB::table('product_category')
            ->join('categories', 'categories.id', '=', 'product_category.category_id')
            ->where('product_category.product_id', $product_id)
            ->orderBy('categories.parent_id')
            ->orderBy('categories.id')
            ->value('categories.slug');
    }

    /** Disable foreign key checks, ignoring any error. */
    private function disableFk(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } catch (Throwable $exception) {
        }
    }

    /** Re-enable foreign key checks, ignoring any error. */
    private function enableFk(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (Throwable $exception) {
        }
    }
}
