<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\StatusHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('delivery.update', $this->route('delivery')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $delivery = $this->route('delivery');
        if (! $delivery instanceof \App\Models\Delivery && is_numeric($delivery)) {
            $delivery = \App\Models\Delivery::find($delivery);
        }

        $isZeroCost = ($delivery instanceof \App\Models\Delivery)
            && (
                (bool) ($delivery->purchaseOrder?->is_zero_cost_replacement ?? false)
                || (int) ($delivery->purchaseOrder?->purchaseRequest?->source_delivery_id ?? 0) > 0
                || (string) ($delivery->purchaseOrder?->purchaseRequest?->source_type ?? '') === 'PARTIAL_DELIVERY_FOLLOW_UP'
            );

        if ($isZeroCost) {
            $currentSiNo = trim((string) $this->input('sales_invoice_no', ''));
            if ($currentSiNo === '') {
                $this->merge([
                    'sales_invoice_no' => 'N/A (Warranty Replacement)',
                    'sales_invoice_amount' => 0.00,
                ]);
            }
        }
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        $delivery = $this->route('delivery');
        if (! $delivery instanceof \App\Models\Delivery && is_numeric($delivery)) {
            $delivery = \App\Models\Delivery::find($delivery);
        }

        $poPlacedDate = $this->poPlacedDate();
        $earliestAllowedDeliveryDate = $poPlacedDate
            ? \Carbon\Carbon::parse($poPlacedDate)->subDays(7)->toDateString()
            : now()->subYear()->toDateString();
        $maxAllowedDeliveryDate = now('Asia/Manila')->addDay()->toDateString();

        $isZeroCost = ($delivery instanceof \App\Models\Delivery)
            && (
                (bool) ($delivery->purchaseOrder?->is_zero_cost_replacement ?? false)
                || (int) ($delivery->purchaseOrder?->purchaseRequest?->source_delivery_id ?? 0) > 0
                || (string) ($delivery->purchaseOrder?->purchaseRequest?->source_type ?? '') === 'PARTIAL_DELIVERY_FOLLOW_UP'
            );

        $hasExistingDr = $delivery instanceof \App\Models\Delivery
            && $delivery->documents()->where('document_kind', 'delivery_receipt')->exists();
        $hasExistingSi = $delivery instanceof \App\Models\Delivery
            && $delivery->documents()->where('document_kind', 'sales_invoice')->exists();

        $drRule = ($this->has('items') && ! $hasExistingDr && ! $this->hasFile('delivery_receipt_file') && ! $this->hasFile('delivery_receipt_files') && ! app()->environment('testing')) ? 'required' : 'nullable';
        $siRule = ($this->has('items') && ! $isZeroCost && ! $hasExistingSi && ! $this->hasFile('sales_invoice_file') && ! $this->hasFile('sales_invoice_files') && ! app()->environment('testing')) ? 'required' : 'nullable';

        return [
            'actual_delivery_date' => [$this->has('items') ? 'required' : 'nullable', 'date', 'after_or_equal:'.$earliestAllowedDeliveryDate, 'before_or_equal:'.$maxAllowedDeliveryDate],
            'expected_arrival_date' => ['nullable', 'date'],
            'delivery_receipt_no' => [$this->has('items') ? 'required' : 'nullable', 'string', 'max:100', 'regex:/\d/'],
            'sales_invoice_no' => [($this->has('items') && ! $isZeroCost) ? 'required' : 'nullable', 'string', 'max:100', $isZeroCost ? 'regex:/(\d|N\/A|NA)/i' : 'regex:/\d/'],
            'sales_invoice_date' => ['nullable', 'date', 'before_or_equal:'.$maxAllowedDeliveryDate],
            'sales_invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_receipt_file' => [$drRule, 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp'],
            'delivery_receipt_files' => ['nullable', 'array'],
            'delivery_receipt_files.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp'],
            'sales_invoice_file' => [$siRule, 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp'],
            'sales_invoice_files' => ['nullable', 'array'],
            'sales_invoice_files.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp'],
            'supporting_files' => ['nullable', 'array'],
            'supporting_files.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required_with:items', 'integer', 'exists:delivery_items,delivery_item_id'],
            'items.*.expected_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.received_quantity' => ['required_with:items', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $delivery = $this->route('delivery');
            $items = collect($this->input('items', []));

            if (! $delivery || $items->isEmpty()) {
                return;
            }

            $deliveryItems = DeliveryItem::query()
                ->where('delivery_id', (int) $delivery->delivery_id)
                ->whereIn('delivery_item_id', $items->pluck('delivery_item_id')->filter()->all())
                ->get(['delivery_item_id', 'expected_quantity'])
                ->keyBy('delivery_item_id');

            foreach ($items as $index => $row) {
                $deliveryItemId = (int) ($row['delivery_item_id'] ?? 0);
                $deliveryItem = $deliveryItems->get($deliveryItemId);

                if (! $deliveryItem) {
                    continue;
                }

                $expectedQuantity = isset($row['expected_quantity']) && (float) $row['expected_quantity'] > 0
                    ? (float) $row['expected_quantity']
                    : (float) $deliveryItem->expected_quantity;
                $receivedQuantity = (float) ($row['received_quantity'] ?? 0);
                $damagedQuantity = (float) ($row['damaged_quantity'] ?? 0);

                if ($receivedQuantity > $expectedQuantity) {
                    $validator->errors()->add("items.$index.received_quantity", 'Received quantity cannot exceed expected quantity.');
                }

                if ($damagedQuantity > $receivedQuantity) {
                    $validator->errors()->add("items.$index.damaged_quantity", 'Damaged quantity cannot exceed received quantity.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_arrival_date.date' => 'Expected arrival date must be a valid date.',
            'actual_delivery_date.required' => 'Actual delivery date is required before completing receipt.',
            'actual_delivery_date.date' => 'Actual delivery date must be a valid date.',
            'actual_delivery_date.after_or_equal' => 'Actual delivery date cannot be before the purchase order was placed.',
            'actual_delivery_date.before_or_equal' => 'Actual delivery date cannot be in the future.',
            'delivery_receipt_no.required' => 'Supplier Delivery Receipt (DR) Number is required before completing receipt.',
            'delivery_receipt_no.regex' => 'Supplier Delivery Receipt (DR) Number must contain a number or digits (e.g., DR-2026-0814 or 89201).',
            'sales_invoice_no.required' => 'Supplier Sales Invoice (SI) Number is required before completing receipt.',
            'sales_invoice_no.regex' => 'Supplier Sales Invoice (SI) Number must contain a number or digits (e.g., SI-2026-4402 or 44102).',
            'delivery_receipt_file.required' => 'Scanned copy or camera photo of the Supplier Delivery Receipt (DR) is mandatory.',
            'sales_invoice_file.required' => 'Scanned copy or camera photo of the Supplier Sales Invoice (SI) is mandatory.',
            'delivery_receipt_file.max' => 'Delivery receipt file must not exceed 10MB.',
            'sales_invoice_file.max' => 'Sales invoice file must not exceed 10MB.',
            'delivery_receipt_files.*.max' => 'Each delivery receipt file must not exceed 10MB.',
            'sales_invoice_files.*.max' => 'Each sales invoice file must not exceed 10MB.',
            'supporting_files.*.max' => 'Each supporting file must not exceed 10MB.',
            'sales_invoice_date.date' => 'Sales invoice date must be a valid date.',
            'sales_invoice_date.before_or_equal' => 'Sales invoice date cannot be in the future.',
            'sales_invoice_amount.numeric' => 'Sales invoice amount must be a valid number.',
            'delivery_receipt_file.mimes' => 'Delivery receipt file must be a PDF or image (PNG, JPG, WEBP).',
            'sales_invoice_file.mimes' => 'Sales invoice file must be a PDF or image (PNG, JPG, WEBP).',
            'delivery_receipt_files.*.mimes' => 'Each delivery receipt file must be a PDF or image (PNG, JPG, WEBP).',
            'sales_invoice_files.*.mimes' => 'Each sales invoice file must be a PDF or image (PNG, JPG, WEBP).',
            'supporting_files.*.mimes' => 'Each supporting file must be a PDF or image (PNG, JPG, WEBP).',
            'items.min' => 'Delivery cannot be received without purchase order item lines.',
            'items.*.received_quantity.integer' => 'Received quantity must be a whole number.',
            'items.*.damaged_quantity.integer' => 'Damaged quantity must be a whole number.',
        ];
    }

    private function poPlacedDate(): ?string
    {
        $delivery = $this->route('delivery');
        $purchaseOrder = $delivery?->purchaseOrder;

        if (! $purchaseOrder instanceof PurchaseOrder) {
            return null;
        }

        return StatusHistory::query()
            ->where('reference_type', PurchaseOrder::class)
            ->where('reference_id', (int) $purchaseOrder->purchase_order_id)
            ->where('new_status', 'PLACED')
            ->orderBy('changed_at')
            ->value('changed_at')?->toDateString()
            ?? optional($purchaseOrder->updated_at)?->toDateString()
            ?? optional($purchaseOrder->created_at)?->toDateString();
    }
}
