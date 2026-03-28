<?php

namespace App\Http\Requests\DistributionItem;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistributionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('distribution.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'received_by' => ['nullable', 'integer', 'exists:users,id'],
            'item_name_snapshot' => ['nullable', 'string', 'max:200'],
            'quantity_released' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
