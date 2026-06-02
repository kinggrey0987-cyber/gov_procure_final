<?php

namespace App\Http\Requests\Claim;

use App\Models\DistributionRecord;
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
        $distributionRecord = DistributionRecord::query()
            ->where('distribution_record_id', (int) $this->input('distribution_record_id'))
            ->first();
        $earliestClaimDate = optional($distributionRecord?->release_date)?->toDateString() ?? now()->toDateString();

        return [
            'claim_code' => ['nullable', 'string', 'max:80', 'unique:claims,claim_code'],
            'distribution_record_id' => ['required', 'integer', 'exists:distribution_records,distribution_record_id'],
            'distribution_item_id' => ['nullable', 'integer', 'exists:distribution_items,distribution_item_id'],
            'parent_claim_id' => ['nullable', 'integer', 'exists:claims,claims_id'],
            'source_type' => ['nullable', 'string', 'max:60'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'claim_type' => ['required', 'string', 'max:80'],
            'claim_date' => ['required', 'date', 'after_or_equal:'.$earliestClaimDate, 'before_or_equal:today'],
            'claim_location' => ['required', 'string', 'max:150'],
            'quantity_disputed' => ['nullable', 'integer', 'min:0'],
            'quantity_damaged' => ['nullable', 'integer', 'min:0'],
            'quantity_missing' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'attachment_title' => ['nullable', 'string', 'max:200'],
            'attachment_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'claim_date.after_or_equal' => 'Date observed cannot be before the supply release date.',
            'claim_date.before_or_equal' => 'Date observed cannot be in the future.',
        ];
    }
}
