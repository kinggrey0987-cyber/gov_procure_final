<?php

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('alert.review', $this->route('alert')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:IN_REVIEW,RESOLVED,DISMISSED'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
