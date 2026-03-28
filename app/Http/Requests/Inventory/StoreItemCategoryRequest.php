<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemCategoryRequest extends FormRequest
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
            'category_name' => ['required', 'string', 'max:150', 'unique:item_categories,category_name'],
            'standard_delivery_days' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
