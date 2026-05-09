<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return (bool) $this->user()?->can('procurement.purchase-order.update', $purchaseOrder);
    }

    public function rules(): array
    {
        return [
            'supply_request_item_id' => ['required', 'integer', 'exists:supply_request_items,supply_request_item_id'],
            'item_id' => ['required', 'integer'],
            'item_name_snapshot' => ['required', 'string', 'max:200'],
            'specification' => ['nullable', 'string'],
            'quantity_ordered' => ['required', 'integer', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
