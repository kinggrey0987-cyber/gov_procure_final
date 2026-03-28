<?php

namespace App\Http\Requests\Governance;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('governance.manage-users');
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query): void {
                    $query->where('code', '!=', UserRole::PUBLIC->value)
                        ->where('is_active', true);
                }),
            ],
            'office_id' => [
                'required',
                'integer',
                Rule::exists('offices', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
