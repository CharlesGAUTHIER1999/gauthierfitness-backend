<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductOptionSeeder extends Seeder
{
    /** Truncate product options and seed size/format/capacity options per product category. */
    public function run(): void
    {
        $this->disableFk();
        DB::table('product_options')->truncate();
        $this->enableFk();
        $men_sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        $women_sizes = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $belt_glove_sizes = ['S', 'M', 'L', 'XL'];
        $products = Product::with('categories.parent')->get();

        foreach ($products as $product) {
            $root = $this->rootSlug($product);

            // Equipments: no options
            if ($root === 'equipments') {
                continue;
            }

            // Nutrition
            if ($root === 'nutrition') {
                $fmt = $this->extractFormat($product->name);
                if ($fmt) {
                    $this->createOption($product->id, [
                        'type' => 'format',
                        'code' => $fmt['code'],
                        'label' => $fmt['label'],
                        'position' => 1,
                        'vat' => $product->vat ?? 20.0,
                        'sku' => $this->makeOptionSku($product->sku, $fmt['code']),
                        'meta' => $fmt['meta'] ?? null,
                    ]);
                }

                continue;
            }

            // Femmes
            if ($root === 'femmes') {

                if (str_contains($product->name, 'Sac de sport')) {
                    $this->seedCapacities($product->id, $product->sku, $product->vat ?? 20.0, ['38L', '45L']);

                    continue;
                }

                if (str_contains($product->name, 'Gourde')) {
                    $this->seedCapacities($product->id, $product->sku, $product->vat ?? 20.0, ['700ML'], ['700ML' => '700 ml']);

                    continue;
                }

                if (str_contains($product->name, 'Bandeau')) {
                    $this->createOption($product->id, [
                        'type' => 'size',
                        'code' => 'UNI',
                        'label' => 'Taille unique',
                        'position' => 1,
                        'vat' => $product->vat ?? 20.0,
                        'sku' => $this->makeOptionSku($product->sku, 'UNI'),
                        'meta' => ['dimensions' => '23 x 8 cm'],
                    ]);

                    continue;
                }

                $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $women_sizes);

                continue;
            }

            // Hommes
            if ($root === 'hommes') {
                if (str_contains($product->name, 'Sac de sport')) {
                    $this->seedCapacities($product->id, $product->sku, $product->vat ?? 20.0, ['38L', '45L']);

                    continue;
                }

                if (str_contains($product->name, 'Ceinture')) {
                    $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $belt_glove_sizes);

                    continue;
                }

                if (str_contains($product->name, 'Gants')) {
                    $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $belt_glove_sizes);

                    continue;
                }

                $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $men_sizes);

            }
        }
    }

    /** Resolve the root category slug (parent slug if any, else the category's own slug). */
    private function rootSlug(Product $product): ?string
    {
        $cat = $product->categories->first();
        if (! $cat) {
            return null;
        }

        return $cat->parent?->slug ?? $cat->slug;
    }

    /** Create a "size" option for each given size value. */
    private function seedSizes(int $product_id, string $product_sku, float $vat, array $sizes): void
    {
        $pos = 1;
        foreach ($sizes as $size) {
            $this->createOption($product_id, [
                'type' => 'size',
                'code' => $size,
                'label' => $size,
                'position' => $pos++,
                'vat' => $vat,
                'sku' => $this->makeOptionSku($product_sku, $size),
            ]);
        }
    }

    /** Create a "capacity" option for each given code, using a custom or auto-generated label. */
    private function seedCapacities(int $product_id, string $product_sku, float $vat, array $codes, array $labels = []): void
    {
        $pos = 1;
        foreach ($codes as $code) {
            $label = $labels[$code] ?? $this->prettyCapacityLabel($code);

            $this->createOption($product_id, [
                'type' => 'capacity',
                'code' => $code,
                'label' => $label,
                'position' => $pos++,
                'vat' => $vat,
                'sku' => $this->makeOptionSku($product_sku, $code),
            ]);
        }
    }

    /** Format a capacity code (e.g. "38L", "700ML") into a human-readable label. */
    private function prettyCapacityLabel(string $code): string
    {
        if (preg_match('/^(\d+)\s*L$/i', $code, $m)) {
            return $m[1].' L';
        }
        if (preg_match('/^(\d+)\s*ML$/i', $code, $m)) {
            return $m[1].' ml';
        }

        return $code;
    }

    /** Extract a weight/volume format (kg, g, ml) from a product name, if present. */
    private function extractFormat(string $name): ?array
    {
        if (preg_match('/(\d+)\s*kg/i', $name, $m)) {
            return ['code' => strtolower($m[1].'kg'), 'label' => $m[1].' kg'];
        }

        if (preg_match('/(\d+)\s*g/i', $name, $m)) {
            return ['code' => strtolower($m[1].'g'), 'label' => $m[1].' g'];
        }

        if (preg_match('/(\d+)\s*ml/i', $name, $m)) {
            return ['code' => strtolower($m[1].'ml'), 'label' => $m[1].' ml'];
        }

        return null;
    }

    /** Build a unique SKU for an option by appending its code to the product SKU. */
    private function makeOptionSku(string $product_sku, string $code): string
    {
        $sku = $product_sku.'-'.strtoupper($code);

        return substr($sku, 0, 80);
    }

    /** Persist a new ProductOption record from the given data array. */
    private function createOption(int $product_id, array $data): void
    {
        ProductOption::create([
            'product_id' => $product_id,
            'type' => $data['type'],
            'code' => $data['code'],
            'label' => $data['label'] ?? null,
            'price_ht' => $data['price_ht'] ?? null,
            'price_ttc' => $data['price_ttc'] ?? null,
            'vat' => $data['vat'] ?? 20.0,
            'position' => $data['position'] ?? 0,
            'meta' => $data['meta'] ?? null,
            'sku' => $data['sku'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** Disable foreign key checks, ignoring any error. */
    private function disableFk(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } catch (Throwable $e) {
        }
    }

    /** Re-enable foreign key checks, ignoring any error. */
    private function enableFk(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (Throwable $e) {
        }
    }
}
