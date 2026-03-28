<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchase_request');

        return (bool) $this->user()?->can('procurement.purchase-request.update', $purchaseRequest);
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string'],
        ];
    }
}
