<?php

namespace App\Http\Requests\Office;

use App\Models\SupplyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplyRequest|null $supplyRequest */
        $supplyRequest = $this->route('supply_request');

        return $supplyRequest
            ? ($this->user()?->can('office.supply-request.update', $supplyRequest) ?? false)
            : false;
    }

    public function rules(): array
    {
        $officeId = (int) $this->user()?->office_id;

        return [
            'ppmp_id' => [
                'required',
                'integer',
                Rule::exists('ppmp', 'ppmp_id')->where(function ($query) use ($officeId): void {
                    $query->where('office_id', $officeId)
                        ->whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'RETURNED_FOR_REVISION']);
                }),
            ],
            'request_date' => ['required', 'date'],
            'needed_by_date' => ['required', 'date', 'after_or_equal:request_date'],
            'request_category' => ['required', 'string', 'max:50'],
            'purpose' => ['required', 'string'],
            'priority_level' => ['required', 'string', 'max:30'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
