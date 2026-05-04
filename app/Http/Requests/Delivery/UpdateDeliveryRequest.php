<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

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
            'items.*.item_condition' => ['nullable', 'string', 'max:50'],
        ];
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
