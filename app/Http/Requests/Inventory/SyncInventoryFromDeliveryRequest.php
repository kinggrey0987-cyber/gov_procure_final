<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class SyncInventoryFromDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('inventory.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'delivery_id' => ['required', 'integer', 'exists:deliveries,delivery_id'],
        ];
    }
}
