<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('delivery.create') ?? false;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,purchase_order_id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,supplier_id'],
            'batch_number' => ['nullable', 'integer', 'min:1'],
            'total_batches' => ['nullable', 'integer', 'min:1'],
            'delivery_schedule_type' => ['nullable', 'string', 'in:SINGLE_DELIVERY,STAGGERED_BATCH'],
            'is_final_batch' => ['nullable', 'boolean'],
            'delivery_code' => ['nullable', 'string', 'unique:deliveries,delivery_code'],
            'actual_delivery_date' => ['nullable', 'date'],
            'expected_arrival_date' => ['nullable', 'date', 'after:actual_delivery_date'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.purchase_order_item_id' => ['required_with:items', 'integer'],
            'items.*.expected_quantity' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'Purchase order is required.',
            'purchase_order_id.exists' => 'Selected purchase order does not exist.',
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'delivery_code.unique' => 'Delivery code must be unique.',
            'expected_arrival_date.after' => 'Expected arrival date must be after actual delivery date.',
        ];
    }
}
