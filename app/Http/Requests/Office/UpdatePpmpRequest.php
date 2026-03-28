<?php

namespace App\Http\Requests\Office;

use App\Models\Ppmp;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePpmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ppmp|null $ppmp */
        $ppmp = $this->route('ppmp');

        return $ppmp ? ($this->user()?->can('office.ppmp.update', $ppmp) ?? false) : false;
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
