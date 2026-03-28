<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->bothify('OFF-###')),
            'description' => $this->faker->text(200),
            'is_active' => true,
        ];
    }
}
