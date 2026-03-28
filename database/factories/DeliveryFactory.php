<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_code' => 'DEL-' . now()->format('Y') . '-' . $this->faker->unique()->numberBetween(10001, 99999),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'actual_delivery_date' => $this->faker->optional()->dateTime(),
            'expected_arrival_date' => $this->faker->optional()->dateTime(),
            'received_by' => User::factory(),
            'receipt_status' => 'PENDING',
            'remarks' => $this->faker->optional()->text(200),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'receipt_status' => 'PENDING',
        ]);
    }

    public function received(): self
    {
        return $this->state(fn (array $attributes) => [
            'receipt_status' => 'RECEIVED',
            'actual_delivery_date' => $this->faker->dateTime(),
        ]);
    }

    public function verified(): self
    {
        return $this->state(fn (array $attributes) => [
            'receipt_status' => 'VERIFIED',
            'actual_delivery_date' => $this->faker->dateTime(),
        ]);
    }
}
