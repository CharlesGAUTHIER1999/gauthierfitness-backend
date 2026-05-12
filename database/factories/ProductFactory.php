<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $price_ht = $this->faker->randomFloat(2, 5, 200);
        $vat = 20.0;
        $price_ttc = round($price_ht * (1 + $vat / 100), 3);
        $name = $this->faker->words(3, true);

        return [
            'supplier_id' => Supplier::inRandomOrder()->first()?->id ?? Supplier::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(5),
            'description' => $this->faker->paragraph(),
            'price_ht' => $price_ht,
            'price_ttc' => $price_ttc,
            'vat' => $vat,
            'sku' => strtoupper($this->faker->bothify('SKU-####-??')),
            'barcode' => $this->faker->ean13(),
            'weight' => $this->faker->randomFloat(2, 0.1, 20),
            'is_active' => true,
        ];
    }
}
