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
        'PARTIALLY_RESOLVED',
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

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:IN_REVIEW,RESOLVED,REJECTED,PENDING_REPLACEMENT,PARTIALLY_RESOLVED'],
            'resolution_type' => ['nullable', 'string', 'in:'.implode(',', self::RESOLUTION_TYPES)],
            'replacement_quantity' => ['nullable', 'numeric', 'min:0'],
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

            if ($status === 'IN_REVIEW') {
                return;
            }

            if ($resolutionType === '') {
                $validator->errors()->add('resolution_type', 'Select a resolution type for this claim decision.');

                return;
            }

            if ($status === 'RESOLVED' && $resolutionType === 'PENDING_REPLACEMENT') {
                $validator->errors()->add('resolution_type', 'Pending replacement cannot close a claim as resolved.');
            }

            if ($status === 'PENDING_REPLACEMENT' && $resolutionType !== 'PENDING_REPLACEMENT') {
                $validator->errors()->add('resolution_type', 'Pending replacement status requires the pending replacement resolution type.');
            }

            if ($status === 'PARTIALLY_RESOLVED' && $resolutionType !== 'PARTIAL_REPLACEMENT') {
                $validator->errors()->add('resolution_type', 'Partially resolved claims require the partial replacement resolution type.');
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
