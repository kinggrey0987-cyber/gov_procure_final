<?php

namespace App\Http\Requests\Office;

use App\Models\Ppmp;
use Illuminate\Foundation\Http\FormRequest;

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
            'unit_cost' => ['required', 'numeric', 'gte:0'],
            'procurement_schedule' => ['required', 'string', 'max:100'],
            'fund_source' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
