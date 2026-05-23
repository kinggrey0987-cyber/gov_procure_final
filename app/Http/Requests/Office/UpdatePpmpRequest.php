<?php

namespace App\Http\Requests\Office;

use App\Enums\FundSource;
use App\Models\Ppmp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'fiscal_year' => ['nullable', 'integer'],
            'date_prepared' => ['nullable', 'date'],
            'fund_source' => ['required', 'string', Rule::in(FundSource::values())],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
