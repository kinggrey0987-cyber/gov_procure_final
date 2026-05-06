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
}
