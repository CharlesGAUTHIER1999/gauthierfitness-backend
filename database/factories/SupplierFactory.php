<?php

namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition():array {
        return [
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'contact_email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }
}