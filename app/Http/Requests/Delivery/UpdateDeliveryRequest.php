<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('delivery.update', $this->route('delivery')) ?? false;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'actual_delivery_date' => ['nullable', 'date'],
            'expected_arrival_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_arrival_date.date' => 'Expected arrival date must be a valid date.',
            'actual_delivery_date.date' => 'Actual delivery date must be a valid date.',
        ];
    }
}
