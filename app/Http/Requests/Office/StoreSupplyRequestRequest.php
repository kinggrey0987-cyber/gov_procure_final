<?php

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('office.supply-request.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'request_date' => ['required', 'date'],
            'needed_by_date' => ['required', 'date', 'after_or_equal:request_date'],
            'request_category' => ['required', 'string', 'max:50'],
            'purpose' => ['required', 'string'],
            'priority_level' => ['required', 'string', 'max:30'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
