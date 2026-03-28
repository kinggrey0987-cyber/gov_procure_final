<?php

namespace App\Http\Requests\Governance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('governance.manage-offices');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('offices', 'name')],
            'code' => ['required', 'string', 'max:50', Rule::unique('offices', 'code')],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
        ]);
    }
}
