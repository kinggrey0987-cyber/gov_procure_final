<?php

namespace App\Http\Requests\Office;

use App\Models\SupplyRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplyRequestItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplyRequest|null $supplyRequest */
        $supplyRequest = $this->route('supply_request');

        return $supplyRequest
            ? ($this->user()?->can('office.supply-request.update', $supplyRequest) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer'],
            'ppmp_item_id' => ['required', 'integer', 'exists:ppmp_items,ppmp_item_id'],
            'item_name_snapshot' => ['required', 'string', 'max:200'],
            'quantity_requested' => ['required', 'numeric', 'gt:0'],
            'estimated_unit_cost' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
