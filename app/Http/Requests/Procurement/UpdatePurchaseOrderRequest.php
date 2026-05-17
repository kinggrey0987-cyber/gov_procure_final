<?php

namespace App\Http\Requests\Procurement;

use App\Enums\ProcurementMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return (bool) $this->user()?->can('procurement.purchase-order.update', $purchaseOrder);
    }

    public function rules(): array
    {
        $purchaseOrder = $this->route('purchase_order');
        $purchaseOrderId = is_object($purchaseOrder)
            ? (int) $purchaseOrder->purchase_order_id
            : (int) $purchaseOrder;

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,supplier_id'],
            'po_number' => ['required', 'string', 'max:80', Rule::unique('purchase_orders', 'po_number')->ignore($purchaseOrderId, 'purchase_order_id')],
            'procurement_method' => ['required', 'string', Rule::in(ProcurementMethod::values())],
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'terms_notes' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
