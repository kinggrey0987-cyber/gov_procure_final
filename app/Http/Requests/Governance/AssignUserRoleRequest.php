<?php

namespace App\Http\Requests\Governance;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUserRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('governance.manage-users') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_code' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (UserRole $role): string => $role->value,
                    UserRole::cases()
                )),
            ],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
