<?php

namespace App\Http\Requests\DeliveryItem;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryItemRequest extends FormRequest
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
            'received_quantity' => ['nullable', 'numeric', 'min:0'],
            'damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'missing_quantity' => ['nullable', 'numeric', 'min:0'],
            'item_condition' => ['nullable', 'string', 'in:GOOD,DAMAGED,MISSING'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_quantity.numeric' => 'Received quantity must be a number.',
            'damaged_quantity.numeric' => 'Damaged quantity must be a number.',
            'missing_quantity.numeric' => 'Missing quantity must be a number.',
            'item_condition.in' => 'Item condition must be GOOD, DAMAGED, or MISSING.',
        ];
    }
}
