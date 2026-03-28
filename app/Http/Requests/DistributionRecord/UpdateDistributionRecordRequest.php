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
        ];
    }
}
