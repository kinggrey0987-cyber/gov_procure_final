<?php

namespace App\Http\Requests\Office;

use App\Models\SupplyRequest;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplyRequest|null $supplyRequest */
        $supplyRequest = $this->route('supply_request');

        return $supplyRequest
            ? ($this->user()?->can('office.supply-request.submit', $supplyRequest) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'redirect_to' => ['nullable', 'in:index,show'],
        ];
    }
}
