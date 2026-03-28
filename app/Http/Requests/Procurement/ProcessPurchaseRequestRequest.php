<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchase_request');

        return (bool) $this->user()?->can('procurement.purchase-request.process', $purchaseRequest);
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string'],
        ];
    }
}
