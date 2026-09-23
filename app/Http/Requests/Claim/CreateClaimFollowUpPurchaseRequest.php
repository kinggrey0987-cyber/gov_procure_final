<?php

namespace App\Http\Requests\Claim;

use Illuminate\Foundation\Http\FormRequest;

class CreateClaimFollowUpPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('claim.follow-up-pr.create', $this->route('claim')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
