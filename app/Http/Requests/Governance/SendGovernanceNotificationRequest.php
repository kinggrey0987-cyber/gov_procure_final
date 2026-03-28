<?php

namespace App\Http\Requests\Governance;

use Illuminate\Foundation\Http\FormRequest;

class SendGovernanceNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('governance.send-notifications') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string'],
            'notification_type' => ['required', 'string', 'max:100'],
            'reference_type' => ['nullable', 'string', 'max:150', 'required_with:reference_id'],
            'reference_id' => ['nullable', 'integer', 'required_with:reference_type'],
        ];
    }
}
