<?php

namespace App\Http\Requests\Governance;

use App\Models\Office;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('governance.manage-offices');
    }

    public function rules(): array
    {
        /** @var Office $office */
        $office = $this->route('office');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('offices', 'name')->ignore($office->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('offices', 'code')->ignore($office->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
        ]);
    }
}
