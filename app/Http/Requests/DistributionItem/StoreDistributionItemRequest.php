<?php

namespace App\Http\Requests\DistributionItem;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributionItemRequest extends FormRequest
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
            'item_id' => ['required', 'integer'],
            'received_by' => ['nullable', 'integer', 'exists:users,id'],
            'item_name_snapshot' => ['required', 'string', 'max:200'],
            'quantity_released' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
