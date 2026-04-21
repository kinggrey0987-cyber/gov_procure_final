<?php

namespace App\Http\Requests\DistributionRecord;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributionRecordRequest extends FormRequest
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
            'distribution_code' => ['nullable', 'string', 'max:80', 'unique:distribution_records,distribution_code'],
            'supply_request_id' => ['required', 'integer', 'exists:supply_requests,supply_request_id'],
            'delivery_id' => ['nullable', 'integer', 'exists:deliveries,delivery_id'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'received_by' => ['nullable', 'integer', 'exists:users,id'],
            'release_date' => ['nullable', 'date'],
            'storage_location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
