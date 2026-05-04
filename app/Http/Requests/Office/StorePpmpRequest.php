<?php

namespace App\Http\Requests\Office;

use App\Enums\FundSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'fund_source' => ['required', 'string', Rule::in(FundSource::values())],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:items,item_id'],
            'items.*.item_category_id' => ['required_with:items', 'integer', 'exists:item_categories,item_category_id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.procurement_schedule' => ['required_with:items', 'string', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
            'items.*.fund_source' => ['nullable', 'string', 'max:100'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item): array {
                if (! is_array($item)) {
                    return [];
                }

                $item['procurement_schedule'] = strtoupper(trim((string) ($item['procurement_schedule'] ?? '')));

                return $item;
            })
            ->all();

        $this->merge(['items' => $items]);
    }

    public function messages(): array
    {
        return [
            'items.*.procurement_schedule.in' => 'Procurement Schedule must be one of: Q1, Q2, Q3, or Q4.',
        ];
    }
}
