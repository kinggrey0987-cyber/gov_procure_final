<?php

namespace App\Http\Requests\Inventory;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('inventory.catalog.manage') ?? false;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Item|null $item */
        $item = $this->route('item');

        return [
            'item_name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('items', 'item_name')->ignore($item?->item_id, 'item_id'),
            ],
            'item_category_id' => ['required', 'integer', 'exists:item_categories,item_category_id'],
            'description' => ['nullable', 'string'],
            'default_unit' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
