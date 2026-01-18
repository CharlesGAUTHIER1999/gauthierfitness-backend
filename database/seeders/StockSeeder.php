<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockLot;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    public function run(): void {
        Product::all()->each(function ($product) {
            StockLot::factory(3)->create([
                'product_id' => $product->id,
            ]);
        });
    }
}