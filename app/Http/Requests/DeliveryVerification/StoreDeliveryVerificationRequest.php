<?php

namespace App\Http\Requests\DeliveryVerification;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'verification_date' => ['required', 'date'],
            'verification_status' => ['required', 'string', 'in:APPROVED,REJECTED'],
            'discrepancy_notes' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verification_date.required' => 'Verification date is required.',
            'verification_date.date' => 'Verification date must be a valid date.',
            'verification_status.required' => 'Verification status is required.',
            'verification_status.in' => 'Verification status must be APPROVED or REJECTED.',
        ];
    }
}
