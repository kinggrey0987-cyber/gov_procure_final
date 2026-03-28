<?php

namespace App\Http\Requests\Executive;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePpmpApprovalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('executive.ppmp-approval.signoff', $this->route('ppmp')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:APPROVED,REJECTED,RETURNED_FOR_REVISION'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
