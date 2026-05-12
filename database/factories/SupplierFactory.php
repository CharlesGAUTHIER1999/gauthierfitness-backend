<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(4),
            'address' => $this->faker->address(),
            'contact_email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }
}
