<?php

namespace App\Http\Requests\Office;

use App\Enums\FundSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePpmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('office.ppmp.create') ?? false;
    }

    public function rules(): array
    {
        $currentYear = (int) now()->format('Y');

        return [
            'fiscal_year' => ['required', 'integer', 'min:'.$currentYear, 'max:3000'],
            'date_prepared' => ['required', 'date'],
            'fund_source' => ['required', 'string', Rule::in(FundSource::values())],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:items,item_id'],
            'items.*.item_category_id' => ['required_with:items', 'integer', 'exists:item_categories,item_category_id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.procurement_schedule' => ['required_with:items', 'string', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
            'items.*.remarks' => ['required_with:items', 'string', 'max:1000'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seen = [];

            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemId = (string) ($item['item_id'] ?? '');
                $schedule = strtoupper(trim((string) ($item['procurement_schedule'] ?? '')));

                if ($itemId === '' || $schedule === '') {
                    continue;
                }

                $key = $itemId.'|'.$schedule;
                if (isset($seen[$key])) {
                    $validator->errors()->add("items.$index.item_id", 'This item is already planned for the selected procurement quarter. Update the existing row instead.');
                    $validator->errors()->add("items.$index.procurement_schedule", 'Duplicate item and procurement schedule combinations are not allowed.');
                }

                $seen[$key] = true;
            }
        });
    }

    public function messages(): array
    {
        return [
            'fiscal_year.min' => 'Fiscal Year cannot be earlier than the current year.',
            'items.required' => 'At least one complete PPMP item row is required.',
            'items.min' => 'At least one complete PPMP item row is required.',
            'items.*.procurement_schedule.in' => 'Procurement Schedule must be one of: Q1, Q2, Q3, or Q4.',
            'items.*.remarks.required_with' => 'Item Remarks are required because they become the item specification for Supply Requests and Purchase Orders.',
        ];
    }
}
