<?php

namespace App\Http\Requests\Claim;

use App\Models\DistributionRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $earliestClaimDate = optional($distributionRecord?->release_date)?->toDateString() ?? now('Asia/Manila')->toDateString();
        $maxAllowedClaimDate = now('Asia/Manila')->addDay()->toDateString();

        return [
            'claim_code' => ['nullable', 'string', 'max:80', 'unique:claims,claim_code'],
            'distribution_record_id' => ['required', 'integer', 'exists:distribution_records,distribution_record_id'],
            'distribution_item_id' => ['nullable', 'integer', 'exists:distribution_items,distribution_item_id'],
            'parent_claim_id' => ['nullable', 'integer', 'exists:claims,claims_id'],
            'source_type' => ['nullable', 'string', 'max:60'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'claim_type' => ['required', 'string', Rule::in(['MISSING_ITEM', 'DAMAGED_ITEM', 'MISSING_AND_DAMAGED'])],
            'claim_date' => ['required', 'date', 'after_or_equal:'.$earliestClaimDate, 'before_or_equal:'.$maxAllowedClaimDate],
            'claim_location' => ['required', 'string', 'max:150'],
            'quantity_disputed' => ['nullable', 'integer', 'min:0'],
            'quantity_damaged' => ['nullable', 'integer', 'min:0'],
            'quantity_missing' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.distribution_item_id' => ['required_with:items', 'integer', 'exists:distribution_items,distribution_item_id'],
            'items.*.claim_type' => ['nullable', 'string', Rule::in(['MISSING_ITEM', 'DAMAGED_ITEM', 'MISSING_AND_DAMAGED'])],
            'items.*.quantity_disputed' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_damaged' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_missing' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'attachment_title' => ['nullable', 'string', 'max:200'],
            'attachment_file' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp',
            ],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $claimType = (string) $this->input('claim_type');
            if (in_array($claimType, ['DAMAGED_ITEM', 'MISSING_ITEM', 'MISSING_AND_DAMAGED'], true)) {
                $hasSingle = $this->hasFile('attachment_file');
                $hasMulti = $this->hasFile('attachments') && count($this->file('attachments')) > 0;
                if (! $hasSingle && ! $hasMulti) {
                    $validator->errors()->add('attachment_file', 'Evidence (photo or document) is required for damaged or missing item claims.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'claim_date.after_or_equal' => 'Date observed cannot be before the supply release date.',
            'claim_date.before_or_equal' => 'Date observed cannot be in the future.',
            'claim_type.in' => 'Claim type must be Missing Item or Damaged Item.',
            'attachment_file.required_if' => 'Evidence (photo or document) is required for damaged or missing item claims. Please attach a file showing the issue.',
        ];
    }
}
