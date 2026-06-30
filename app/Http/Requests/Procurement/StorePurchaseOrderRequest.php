<?php

namespace App\Http\Requests\Procurement;

use App\Enums\ProcurementMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('procurement.purchase-order.create');
    }

    public function rules(): array
    {
        return [
            'purchase_request_id' => ['required', 'integer', 'exists:purchase_requests,purchase_request_id'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'supplier_id')->where('status', 'ACTIVE')],
            'po_number' => ['nullable', 'string', 'max:80', Rule::unique('purchase_orders', 'po_number')],
            'procurement_method' => ['required', 'string', Rule::in(ProcurementMethod::values())],
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'terms_notes' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
