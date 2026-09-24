<?php

namespace App\Http\Requests\Office;

use App\Models\Ppmp;
use App\Models\SupplyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSupplyRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        /** @var SupplyRequest|null $supplyRequest */
        $supplyRequest = $this->route('supply_request');

        if (! $this->has('ppmp_id') && $supplyRequest?->ppmp_id) {
            $this->merge([
                'ppmp_id' => (int) $supplyRequest->ppmp_id,
            ]);
        }
    }

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
                    $query->where('office_id', $officeId);
                }),
            ],
            'needed_by_date' => ['required', 'date'],
            'request_category' => ['required', 'string', 'max:50'],
            'purpose' => ['required', 'string'],
            'priority_level' => ['required', 'string', 'max:30'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var SupplyRequest|null $supplyRequest */
            $supplyRequest = $this->route('supply_request');

            if (! $supplyRequest || ! $this->filled('needed_by_date') || ! $supplyRequest->request_date) {
                return;
            }

            $ppmpId = (int) $this->input('ppmp_id');
            if ($ppmpId > 0) {
                $currentYear = (int) now()->format('Y');
                $ppmpIsApproved = Ppmp::query()
                    ->where('ppmp_id', $ppmpId)
                    ->where('office_id', (int) $this->user()?->office_id)
                    ->where('status', 'APPROVED')
                    ->where('fiscal_year', $currentYear)
                    ->exists();

                if (! $ppmpIsApproved) {
                    $validator->errors()->add('ppmp_id', "Selected PPMP is not an approved PPMP for active Fiscal Year {$currentYear} and cannot be used for a supply request.");
                    return;
                }
            }

            $neededByDate = Carbon::parse($this->input('needed_by_date'))->startOfDay();
            $requestDate = $supplyRequest->request_date->copy()->startOfDay();

            if ($neededByDate->lt($requestDate)) {
                $validator->errors()->add('needed_by_date', 'Needed By Date must be on or after the recorded request date.');
            }
        });
    }
}
