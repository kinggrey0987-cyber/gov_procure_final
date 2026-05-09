<?php

namespace App\Http\Requests\DistributionRecord;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistributionRecordRequest extends FormRequest
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
            'release_date' => ['nullable', 'date'],
            'storage_location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'item_reconciliations' => ['nullable', 'array'],
            'item_reconciliations.*.distribution_item_id' => ['required_with:item_reconciliations', 'integer', 'exists:distribution_items,distribution_item_id'],
            'item_reconciliations.*.quantity_received' => ['required_with:item_reconciliations', 'integer', 'min:0'],
            'item_reconciliations.*.quantity_damaged' => ['nullable', 'integer', 'min:0'],
            'item_reconciliations.*.quantity_missing' => ['nullable', 'integer', 'min:0'],
            'item_reconciliations.*.receipt_condition' => ['nullable', 'string', 'max:50'],
            'item_reconciliations.*.receipt_notes' => ['nullable', 'string'],
        ];
    }
}
