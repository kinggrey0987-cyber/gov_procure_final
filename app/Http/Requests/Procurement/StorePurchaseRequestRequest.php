<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('procurement.purchase-request.create');
    }

    public function rules(): array
    {
        return [
            'supply_request_id' => ['required', 'integer', 'exists:supply_requests,supply_request_id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
