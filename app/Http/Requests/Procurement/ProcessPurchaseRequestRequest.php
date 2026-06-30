<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProcessPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchase_request');

        if ($this->input('action') === 'process' && (string) $purchaseRequest->status !== 'OPEN') {
            return false;
        }

        return (bool) $this->user()?->can('procurement.purchase-request.process', $purchaseRequest);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:process,return,reject'],
            'remarks' => ['nullable', 'string', 'required_if:action,return,reject'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = (string) $this->input('action', '');

            if (! in_array($action, ['return', 'reject'], true)) {
                return;
            }

            $remarks = trim((string) $this->input('remarks', ''));
            if ($remarks === '') {
                $validator->errors()->add('remarks', 'A reason is required when returning for revision or rejecting a request.');
            }
        });
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
