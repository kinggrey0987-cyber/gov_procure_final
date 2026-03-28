<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryVerification>
 */
class DeliveryVerificationFactory extends Factory
{
    protected $model = DeliveryVerification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'verified_by' => User::factory(),
            'verification_date' => $this->faker->dateTime(),
            'verification_status' => $this->faker->randomElement(['APPROVED', 'REJECTED']),
            'discrepancy_notes' => $this->faker->optional(0.4)->text(300),
            'remarks' => $this->faker->optional(0.3)->text(200),
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'APPROVED',
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'REJECTED',
        ]);
    }
}
