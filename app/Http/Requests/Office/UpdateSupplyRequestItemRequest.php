<?php

namespace App\Http\Requests\Office;

use App\Models\SupplyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        /** @var SupplyRequest|null $supplyRequest */
        $supplyRequest = $this->route('supply_request');
        $ppmpId = (int) ($supplyRequest?->ppmp_id ?? 0);

        return [
            'item_id' => ['nullable', 'integer'],
            'ppmp_item_id' => [
                'required',
                'integer',
                Rule::exists('ppmp_items', 'ppmp_item_id')->where(function ($query) use ($ppmpId): void {
                    if ($ppmpId > 0) {
                        $query->where('ppmp_id', $ppmpId);
                    }
                }),
            ],
            'item_name_snapshot' => ['nullable', 'string', 'max:200'],
            'quantity_requested' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var SupplyRequest|null $supplyRequest */
            $supplyRequest = $this->route('supply_request');

            if (! $supplyRequest) {
                return;
            }

            if (! $supplyRequest->ppmp_id) {
                $validator->errors()->add('ppmp_id', 'Select a PPMP on the supply request before updating items.');
            }
        });
    }
}
