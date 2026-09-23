<?php

namespace App\Http\Requests\DeliveryVerification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('delivery-verification.create', $this->route('delivery')) ?? false;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        $delivery = $this->route('delivery');
        $earliestAllowedVerificationDate = $delivery?->actual_delivery_date?->toDateString() ?? now()->toDateString();

        return [
            'verification_date' => ['required', 'date', 'after_or_equal:'.$earliestAllowedVerificationDate, 'before_or_equal:today'],
            'verification_status' => ['required', 'string', 'in:APPROVED,REJECTED'],
            'discrepancy_notes' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'evidence_file' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp',
            ],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*' => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $delivery = $this->route('delivery');
            $actualDeliveryDate = $delivery?->actual_delivery_date?->toDateString();
            $verificationDate = $this->date('verification_date')?->toDateString();

            if ($actualDeliveryDate && $verificationDate && $verificationDate < $actualDeliveryDate) {
                $validator->errors()->add('verification_date', 'Verification date cannot be earlier than the actual delivery date.');
            }

            $hasDiscrepancy = $delivery?->items()
                ->where(function ($query): void {
                    $query->where('damaged_quantity', '>', 0)
                        ->orWhere('missing_quantity', '>', 0);
                })
                ->exists() ?? false;

            if ($this->input('verification_status') === 'REJECTED') {
                if (! $hasDiscrepancy) {
                    $validator->errors()->add('verification_status', 'Deliveries without discrepancies (0 damaged, 0 missing) cannot be rejected. All item quantities match the purchase order.');
                }

                if (trim((string) $this->input('discrepancy_notes')) === '') {
                    $validator->errors()->add('discrepancy_notes', 'Discrepancy notes / findings are required when rejecting a delivery.');
                }

                $hasSingle = $this->hasFile('evidence_file');
                $hasMulti = $this->hasFile('evidence_files') && count(array_filter((array) $this->file('evidence_files'))) > 0;
                if (! $hasSingle && ! $hasMulti) {
                    $validator->errors()->add('evidence_file', 'Evidence (photo or document) is required when rejecting a delivery due to discrepancies. Upload a file showing the issue.');
                }
            }

            if ($this->input('verification_status') === 'APPROVED' && $hasDiscrepancy) {
                $validator->errors()->add('verification_status', 'Deliveries with damaged or missing quantities must be rejected for Procurement Unit follow-up.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verification_date.required' => 'Verification date is required.',
            'verification_date.date' => 'Verification date must be a valid date.',
            'verification_date.after_or_equal' => 'Verification date cannot be earlier than the actual delivery date.',
            'verification_date.before_or_equal' => 'Verification date cannot be in the future.',
            'verification_status.required' => 'Verification status is required.',
            'verification_status.in' => 'Verification status must be APPROVED or REJECTED.',
            'evidence_file.required_if' => 'Evidence (photo or document) is required when rejecting a delivery due to discrepancies. Upload a file showing the issue.',
        ];
    }
}
