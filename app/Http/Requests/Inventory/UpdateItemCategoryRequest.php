<?php

namespace App\Http\Requests\Inventory;

use App\Models\ItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemCategoryRequest extends FormRequest
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
        /** @var ItemCategory|null $category */
        $category = $this->route('item_category');

        return [
            'category_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('item_categories', 'category_name')->ignore($category?->item_category_id, 'item_category_id'),
            ],
            'standard_delivery_days' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
