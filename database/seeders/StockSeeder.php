<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StockSeeder extends Seeder
{
    /** Truncate stock lots and generate lots for every product (per option if applicable). */
    public function run(): void
    {
        $this->disableFk();
        DB::table('stock_lots')->truncate();
        $this->enableFk();
        $products = Product::with(['options', 'categories.parent'])->get();

        foreach ($products as $product) {
            $root = $this->rootSlug($product);
            $isNutrition = ($root == 'nutrition');
            $options = $product->options;

            if ($options->count() > 0) {
                foreach ($options as $option) {
                    $this->createLots(
                        productId: $product->id,
                        optionId: $option->id,
                        isNutrition: $isNutrition,
                        lotsCount: 2
                    );
                }
            } else {
                $this->createLots(
                    productId: $product->id,
                    optionId: null,
                    isNutrition: $isNutrition,
                    lotsCount: 2
                );
            }
        }
    }

    /** Resolve the root category slug (parent slug if any, else the category's own slug). */
    private function rootSlug(Product $product): ?string
    {
        $cat = $product->categories->first();
        if (!$cat) {
            return null;
        }

        return $cat->parent?->slug ?? $cat->slug;
    }

    /** Insert a given number of random stock lots for a product (and optional variant). */
    private function createLots(int $productId, ?int $optionId, bool $isNutrition, int $lotsCount = 2): void
    {
        $now = now();

        for ($i = 0; $i < $lotsCount; $i++) {

            $qty = rand(0, 40);

            DB::table('stock_lots')->insert([
                'product_id' => $productId,
                'product_option_id' => $optionId,
                'lot_number' => 'LOT-' . strtoupper(Str::random(10)),
                'expiration_date' => $isNutrition ? now()->addDays(rand(30, 365))->toDateString() : null,
                'initial_quantity' => $qty,
                'quantity' => $qty,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
