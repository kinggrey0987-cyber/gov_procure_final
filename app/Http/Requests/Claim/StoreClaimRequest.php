<?php

namespace App\Http\Requests\Claim;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('claim.create') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'claim_code' => ['nullable', 'string', 'max:80', 'unique:claims,claim_code'],
            'distribution_record_id' => ['required', 'integer', 'exists:distribution_records,distribution_record_id'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'claim_type' => ['required', 'string', 'max:80'],
            'claim_date' => ['required', 'date'],
            'claim_location' => ['required', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
            'attachment_title' => ['nullable', 'string', 'max:200'],
            'attachment_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg'],
        ];
    }
}
