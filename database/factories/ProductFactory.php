<?php

namespace Database\Factories;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition():array {
        $price_ht = $this->faker->randomfloat(2, 5, 200);
        $vat = 20.0;
        $price_ttc = $price_ht * (1 + $vat / 100);

        return [
            'supplier_id' => Supplier::inRandomOrder()->first()?->id ?? Supplier::factory(),
            'name' => $this->faker->words(3, true),
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