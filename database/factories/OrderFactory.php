<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $totalHt  = $this->faker->randomFloat(2, 10, 500);
        $totalTtc = round($totalHt * 1.20, 2);

        return [
            'user_id'        => User::factory(),
            'total_ht'       => $totalHt,
            'total_ttc'      => $totalTtc,
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'failed']),
            'order_status'   => $this->faker->randomElement(['new', 'processing', 'shipped', 'delivered']),
        ];
    }
}
