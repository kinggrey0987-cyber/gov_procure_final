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
        if (! ($purchaseOrder instanceof \App\Models\PurchaseOrder) && ! empty($purchaseOrder)) {
            $purchaseOrder = \App\Models\PurchaseOrder::query()->find($purchaseOrder);
        }

        $supplierRules = ['required', 'integer', 'exists:suppliers,supplier_id'];
        if ($purchaseOrder instanceof \App\Models\PurchaseOrder) {
            $purchaseOrder->loadMissing('purchaseRequest');
            if ($purchaseOrder->is_zero_cost_replacement) {
                $supplierRules[] = Rule::in([(int) $purchaseOrder->supplier_id]);
            }
        }

        return [
            'supplier_id' => $supplierRules,
            'po_number' => ['nullable', 'string', 'max:80'],
            'procurement_method' => ['required', 'string', Rule::in(ProcurementMethod::values())],
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'terms_notes' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
