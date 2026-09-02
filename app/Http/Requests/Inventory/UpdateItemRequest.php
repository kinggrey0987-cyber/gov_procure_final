<?php

namespace App\Http\Requests\Inventory;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('default_unit')) {
            $this->merge([
                'default_unit' => strtolower(trim((string) $this->input('default_unit'))),
            ]);
        }
    }

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
            'default_unit' => ['required', 'string', 'max:50', Rule::exists('item_units', 'name')->where('status', 'active')],
            'pieces_per_box' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
