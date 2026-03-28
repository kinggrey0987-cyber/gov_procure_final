<?php

namespace App\Http\Requests\Claim;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClaimStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('claim.update', $this->route('claim')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:IN_REVIEW,RESOLVED,REJECTED'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
