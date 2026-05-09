<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\StatusHistory;
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
        $earliestAllowedDeliveryDate = $this->poPlacedDate() ?? now()->toDateString();

        return [
            'actual_delivery_date' => [$this->has('items') ? 'required' : 'nullable', 'date', 'after_or_equal:'.$earliestAllowedDeliveryDate, 'before_or_equal:today'],
            'expected_arrival_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required_with:items', 'integer', 'exists:delivery_items,delivery_item_id'],
            'items.*.received_quantity' => ['required_with:items', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
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
            'actual_delivery_date.after_or_equal' => 'Actual delivery date cannot be before the purchase order was placed.',
            'actual_delivery_date.before_or_equal' => 'Actual delivery date cannot be in the future.',
            'items.min' => 'Delivery cannot be received without purchase order item lines.',
            'items.*.received_quantity.integer' => 'Received quantity must be a whole number.',
            'items.*.damaged_quantity.integer' => 'Damaged quantity must be a whole number.',
        ];
    }

    private function poPlacedDate(): ?string
    {
        $delivery = $this->route('delivery');
        $purchaseOrder = $delivery?->purchaseOrder;

        if (! $purchaseOrder instanceof PurchaseOrder) {
            return null;
        }

        return StatusHistory::query()
            ->where('reference_type', PurchaseOrder::class)
            ->where('reference_id', (int) $purchaseOrder->purchase_order_id)
            ->where('new_status', 'PLACED')
            ->orderBy('changed_at')
            ->value('changed_at')?->toDateString()
            ?? optional($purchaseOrder->updated_at)?->toDateString()
            ?? optional($purchaseOrder->created_at)?->toDateString();
    }
}
