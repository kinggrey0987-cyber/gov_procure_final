<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryItem>
 */
class DeliveryItemFactory extends Factory
{
    protected $model = DeliveryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'expected_quantity' => $this->faker->numberBetween(10, 200),
            'received_quantity' => $this->faker->numberBetween(8, 200),
            'damaged_quantity' => $this->faker->optional(0.3)->numberBetween(0, 5),
            'missing_quantity' => $this->faker->optional(0.2)->numberBetween(0, 5),
            'item_condition' => $this->faker->randomElement(['GOOD', 'DAMAGED', 'MISSING']),
            'remarks' => $this->faker->optional(0.3)->text(150),
        ];
    }
}
