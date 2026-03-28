<?php

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;

class StorePpmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('office.ppmp.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:3000'],
            'date_prepared' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:items,item_id'],
            'items.*.item_category_id' => ['required_with:items', 'integer', 'exists:item_categories,item_category_id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.procurement_schedule' => ['required_with:items', 'string', 'max:100'],
            'items.*.fund_source' => ['required_with:items', 'string', 'max:100'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
