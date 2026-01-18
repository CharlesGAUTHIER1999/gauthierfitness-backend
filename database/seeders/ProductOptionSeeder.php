<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductOptionSeeder extends Seeder
{
    public function run():void {
        $this->disableFk();
        DB::table('product_options')->truncate();
        $this->enableFk();
        $menSizes = ['XS','S','M','L','XL','XXL','XXXL'];
        $womenSizes = ['XXS','XS','S','M','L','XL','XXL'];
        $beltGloveSizes = ['S','M','L','XL'];
        $products = Product::with('categories.parent')->get();

        foreach ($products as $product) {
            $root = $this->rootSlug($product);

            // Equipments : pas d'option
            if ($root === 'equipments') continue;

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
                    $this->seedCapacities($product->id, $product->sku, $product->vat ?? 20.0, ['38L','45L']);
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

                $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $womenSizes);
                continue;
            }

            // Hommes
            if ($root === 'hommes') {
                if (str_contains($product->name, 'Sac de sport')) {
                    $this->seedCapacities($product->id, $product->sku, $product->vat ?? 20.0, ['38L','45L']);
                    continue;
                }

                if (str_contains($product->name, 'Ceinture')) {
                    $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $beltGloveSizes);
                    continue;
                }

                if (str_contains($product->name, 'Gants')) {
                    $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $beltGloveSizes);
                    continue;
                }

                $this->seedSizes($product->id, $product->sku, $product->vat ?? 20.0, $menSizes);
                continue;
            }
        }
    }

    private function rootSlug(Product $product): ?string {
        $cat = $product->categories->first();
        if (!$cat) return null;
        return $cat->parent?->slug ?? $cat->slug;
    }

    private function seedSizes(int $productId, string $productSku, float $vat, array $sizes): void {
        $pos = 1;
        foreach ($sizes as $size) {
            $this->createOption($productId, [
                'type' => 'size',
                'code' => $size,
                'label' => $size,
                'position' => $pos++,
                'vat' => $vat,
                'sku' => $this->makeOptionSku($productSku, $size),
            ]);
        }
    }

    private function seedCapacities(int $productId, string $productSku, float $vat, array $codes, array $labels = []): void {
        $pos = 1;
        foreach ($codes as $code) {
            $label = $labels[$code] ?? $this->prettyCapacityLabel($code);

            $this->createOption($productId, [
                'type' => 'capacity',
                'code' => $code,
                'label' => $label,
                'position' => $pos++,
                'vat' => $vat,
                'sku' => $this->makeOptionSku($productSku, $code),
            ]);
        }
    }

    private function prettyCapacityLabel(string $code): string {
        if (preg_match('/^(\d+)\s*L$/i', $code, $m)) return $m[1] . ' L';
        if (preg_match('/^(\d+)\s*ML$/i', $code, $m)) return $m[1] . ' ml';
        return $code;
    }

    private function extractFormat(string $name): ?array {
        if (preg_match('/(\d+)\s*kg/i', $name, $m)) {
            return ['code' => strtolower($m[1] . 'kg'), 'label' => $m[1] . ' kg'];
        }

        if (preg_match('/(\d+)\s*g/i', $name, $m)) {
            return ['code' => strtolower($m[1] . 'g'), 'label' => $m[1] . ' g'];
        }

        if (preg_match('/(\d+)\s*ml/i', $name, $m)) {
            return ['code' => strtolower($m[1] . 'ml'), 'label' => $m[1] . ' ml'];
        }

        return null;
    }

    private function makeOptionSku(string $productSku, string $code): string {
        $sku = $productSku . '-' . strtoupper($code);
        return substr($sku, 0, 80);
    }

    private function createOption(int $productId, array $data): void {
        ProductOption::create([
            'product_id' => $productId,
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

    private function disableFk():void {
        try { DB::statement('SET FOREIGN_KEY_CHECKS=0;'); } catch (Throwable $e) {}
    }

    private function enableFk():void {
        try { DB::statement('SET FOREIGN_KEY_CHECKS=1;'); } catch (Throwable $e) {}
    }
}