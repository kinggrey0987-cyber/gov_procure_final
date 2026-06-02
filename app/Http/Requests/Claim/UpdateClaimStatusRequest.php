<?php

namespace App\Http\Requests\Claim;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateClaimStatusRequest extends FormRequest
{
    private const CLOSING_STATUSES = [
        'RESOLVED',
        'REJECTED',
        'PENDING_REPLACEMENT',
    ];

    private const RESOLUTION_TYPES = [
        'NO_STOCK_ACTION',
        'REPLACEMENT_RELEASED',
        'PARTIAL_REPLACEMENT',
        'PENDING_REPLACEMENT',
        'COUNT_CORRECTED',
        'OTHER_RESOLUTION',
    ];

    public function authorize(): bool
    {
        return auth()->user()?->can('claim.update', $this->route('claim')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $status = strtoupper(trim((string) $this->input('status')));
        $resolutionType = strtoupper(trim((string) $this->input('resolution_type')));

        if ($resolutionType === '') {
            $resolutionType = match ($status) {
                'RESOLVED' => 'REPLACEMENT_RELEASED',
                'PENDING_REPLACEMENT' => 'PENDING_REPLACEMENT',
                'REJECTED' => 'OTHER_RESOLUTION',
                default => '',
            };
        }

        $this->merge([
            'status' => $status,
            'resolution_type' => $resolutionType,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:RESOLVED,REJECTED,PENDING_REPLACEMENT'],
            'resolution_type' => ['nullable', 'string', 'in:'.implode(',', self::RESOLUTION_TYPES)],
            'replacement_quantity' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = strtoupper(trim((string) $this->input('status')));
            $resolutionType = strtoupper(trim((string) $this->input('resolution_type')));
            $replacementQuantity = (float) $this->input('replacement_quantity', 0);

            if (in_array($status, self::CLOSING_STATUSES, true) && trim((string) $this->input('remarks')) === '') {
                $validator->errors()->add('remarks', 'Remarks are required when closing or deferring a claim.');
            }

            if ($resolutionType === '') {
                $validator->errors()->add('resolution_type', 'Select a resolution type for this claim decision.');

                return;
            }

            if ($status === 'RESOLVED' && $resolutionType !== 'REPLACEMENT_RELEASED') {
                $validator->errors()->add('resolution_type', 'Resolved claims require released replacement stock.');
            }

            if ($status === 'PENDING_REPLACEMENT' && $resolutionType !== 'PENDING_REPLACEMENT') {
                $validator->errors()->add('resolution_type', 'Pending replacement status requires the pending replacement resolution type.');
            }

            if ($status === 'REJECTED' && in_array($resolutionType, ['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT', 'PENDING_REPLACEMENT'], true)) {
                $validator->errors()->add('resolution_type', 'Rejected claims cannot use a replacement resolution.');
            }

            if (in_array($resolutionType, ['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT'], true) && $replacementQuantity <= 0) {
                $validator->errors()->add('replacement_quantity', 'Replacement quantity must be greater than zero.');
            }

            if (! in_array($resolutionType, ['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT'], true) && $replacementQuantity > 0) {
                $validator->errors()->add('replacement_quantity', 'Replacement quantity is only allowed for replacement resolutions.');
            }
        });
    }
}
