<?php

namespace App\Http\Requests\Governance;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInternalUserRequest extends FormRequest
{
    private const TECHNICAL_OFFICE_CODE = 'TECH_ADMIN';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('governance.manage-users');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'emp_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'emp_id')],
            'password' => [
                'required',
                'string',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query): void {
                    $query->where('code', '!=', UserRole::PUBLIC->value)
                        ->where('is_active', true);
                }),
            ],
            'office_id' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $roleCode = $this->selectedRoleCode();

            if (! $roleCode || $roleCode === UserRole::PUBLIC->value) {
                return;
            }

            $officeId = $this->input('office_id');
            $officeCode = $this->selectedOfficeCode();

            if ($roleCode === UserRole::ADMIN->value) {
                if ($officeId !== null && $officeId !== '' && $officeCode !== self::TECHNICAL_OFFICE_CODE) {
                    $validator->errors()->add(
                        'office_id',
                        'Admin accounts are technical-only and cannot be assigned to operational offices.',
                    );
                }

                return;
            }

            if ($officeId === null || $officeId === '') {
                $validator->errors()->add('office_id', 'Office assignment is required for this role.');

                return;
            }

            if ($officeCode === self::TECHNICAL_OFFICE_CODE) {
                $validator->errors()->add('office_id', 'Technical Administration is reserved for Admin accounts.');
            }
        });
    }

    private function selectedRoleCode(): ?string
    {
        $roleId = $this->input('role_id');

        if (! is_numeric($roleId)) {
            return null;
        }

        return Role::query()->whereKey((int) $roleId)->value('code');
    }

    private function selectedOfficeCode(): ?string
    {
        $officeId = $this->input('office_id');

        if (! is_numeric($officeId)) {
            return null;
        }

        return Office::query()->whereKey((int) $officeId)->value('code');
    }
}
