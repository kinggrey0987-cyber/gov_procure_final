<?php

namespace App\Http\Requests\Office;

use App\Models\Ppmp;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPpmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ppmp|null $ppmp */
        $ppmp = $this->route('ppmp');

        return $ppmp ? ($this->user()?->can('office.ppmp.submit', $ppmp) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
