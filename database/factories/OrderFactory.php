<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /** Define the default state for a fake order (random totals and statuses). */
    public function definition(): array
    {
        $total_ht = $this->faker->randomFloat(2, 10, 500);
        $total_ttc = round($total_ht * 1.20, 2);

        return [
            'user_id' => User::factory(),
            'total_ht' => $total_ht,
            'total_ttc' => $total_ttc,
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'failed']),
            'order_status' => $this->faker->randomElement(['new', 'processing', 'shipped', 'delivered']),
        ];
    }
}
