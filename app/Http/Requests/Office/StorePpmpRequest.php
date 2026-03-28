<?php

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;

class StorePpmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('office.ppmp.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:3000'],
            'date_prepared' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
