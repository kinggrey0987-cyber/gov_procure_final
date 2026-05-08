<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('delivery.update', $this->route('delivery')) ?? false;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'actual_delivery_date' => [$this->has('items') ? 'required' : 'nullable', 'date'],
            'expected_arrival_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required_with:items', 'integer', 'exists:delivery_items,delivery_item_id'],
            'items.*.received_quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $delivery = $this->route('delivery');
            $items = collect($this->input('items', []));

            if (! $delivery || $items->isEmpty()) {
                return;
            }

            $deliveryItems = DeliveryItem::query()
                ->where('delivery_id', (int) $delivery->delivery_id)
                ->whereIn('delivery_item_id', $items->pluck('delivery_item_id')->filter()->all())
                ->get(['delivery_item_id', 'expected_quantity'])
                ->keyBy('delivery_item_id');

            foreach ($items as $index => $row) {
                $deliveryItemId = (int) ($row['delivery_item_id'] ?? 0);
                $deliveryItem = $deliveryItems->get($deliveryItemId);

                if (! $deliveryItem) {
                    continue;
                }

                $expectedQuantity = (float) $deliveryItem->expected_quantity;
                $receivedQuantity = (float) ($row['received_quantity'] ?? 0);
                $damagedQuantity = (float) ($row['damaged_quantity'] ?? 0);

                if ($receivedQuantity > $expectedQuantity) {
                    $validator->errors()->add("items.$index.received_quantity", 'Received quantity cannot exceed expected quantity.');
                }

                if ($damagedQuantity > $receivedQuantity) {
                    $validator->errors()->add("items.$index.damaged_quantity", 'Damaged quantity cannot exceed received quantity.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_arrival_date.date' => 'Expected arrival date must be a valid date.',
            'actual_delivery_date.required' => 'Actual delivery date is required before completing receipt.',
            'actual_delivery_date.date' => 'Actual delivery date must be a valid date.',
            'items.min' => 'Delivery cannot be received without purchase order item lines.',
        ];
    }
}
