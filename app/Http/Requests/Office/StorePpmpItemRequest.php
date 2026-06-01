<?php

namespace App\Http\Requests\Office;

use App\Models\Ppmp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePpmpItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ppmp|null $ppmp */
        $ppmp = $this->route('ppmp');

        return $ppmp ? ($this->user()?->can('office.ppmp.update', $ppmp) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer'],
            'item_category_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'gt:0'],
            'procurement_schedule' => ['required', 'string', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
            'remarks' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'procurement_schedule' => strtoupper(trim((string) $this->input('procurement_schedule', ''))),
        ]);
    }

    public function messages(): array
    {
        return [
            'unit_cost.gt' => 'Unit Cost must be greater than zero.',
            'procurement_schedule.in' => 'Procurement Schedule must be one of: Q1, Q2, Q3, or Q4.',
            'remarks.required' => 'Item Remarks are required because they become the item specification for Supply Requests and Purchase Orders.',
        ];
    }
}
