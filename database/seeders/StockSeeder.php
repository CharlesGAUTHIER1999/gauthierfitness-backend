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
            $is_nutrition = ($root == 'nutrition');
            $options = $product->options;

            if ($options->count() > 0) {
                foreach ($options as $option) {
                    $this->createLots(
                        product_id: $product->id,
                        option_id: $option->id,
                        is_nutrition: $is_nutrition
                    );
                }
            } else {
                $this->createLots(
                    product_id: $product->id,
                    option_id: null,
                    is_nutrition: $is_nutrition
                );
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

    /** Insert a given number of random stock lots for a product (and optional variant). */
    private function createLots(int $product_id, ?int $option_id, bool $is_nutrition): void
    {
        $now = now();

        for ($i = 0; $i < 2; $i++) {

            $qty = rand(0, 40);

            DB::table('stock_lots')->insert([
                'product_id' => $product_id,
                'product_option_id' => $option_id,
                'lot_number' => 'LOT-'.strtoupper(Str::random(10)),
                'expiration_date' => $is_nutrition ? now()->addDays(rand(30, 365))->toDateString() : null,
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
