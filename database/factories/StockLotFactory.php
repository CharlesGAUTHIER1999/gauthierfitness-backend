<?php

namespace Database\Factories;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLotFactory extends Factory
{
    public function definition():array {
        return [
            'product_id' => Product::factory(),
            'lot_number' => strtoupper($this->faker->bothify('LOT-###??')),
            'expiration_date' => $this->faker->dateTimeBetween('+1 month', '+18 months'),
            'quantity' => $this->faker->numberBetween(10, 300),
        ];
    }
}