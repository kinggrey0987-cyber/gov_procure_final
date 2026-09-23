<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class CreateDeliveryFollowUpPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('delivery.follow-up-pr.create', $this->route('delivery')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $delivery = $this->route('delivery');
            if ($delivery) {
                $hasDiscrepancy = $delivery->items()
                    ->where(function ($query): void {
                        $query->where('damaged_quantity', '>', 0)
                            ->orWhere('missing_quantity', '>', 0);
                    })
                    ->exists();

                if (! $hasDiscrepancy) {
                    $validator->errors()->add('delivery_id', 'No discrepancy quantities (damaged or missing items) were found on this delivery to generate a follow-up purchase request.');
                }
            }
        });
    }
}
