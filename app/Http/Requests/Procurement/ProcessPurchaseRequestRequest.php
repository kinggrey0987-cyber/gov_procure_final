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
            'action' => ['required', 'string', 'in:process,return,reject'],
            'remarks' => ['nullable', 'string', 'required_if:action,return,reject'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remarks.required_if' => 'Remarks are required when returning for revision or rejecting a request.',
        ];
    }
}
