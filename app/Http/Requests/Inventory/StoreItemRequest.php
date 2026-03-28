<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
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
        return [
            'item_name' => ['required', 'string', 'max:200', 'unique:items,item_name'],
            'item_category_id' => ['required', 'integer', 'exists:item_categories,item_category_id'],
            'description' => ['nullable', 'string'],
            'default_unit' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
