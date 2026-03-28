<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class PlacePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return (bool) $this->user()?->can('procurement.purchase-order.place', $purchaseOrder);
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string'],
        ];
    }
}
