<?php

namespace App\Http\Requests\Executive;

use App\Models\Ppmp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePpmpApprovalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routePpmp = $this->route('ppmp');
        $ppmp = $routePpmp instanceof Ppmp
            ? $routePpmp
            : (is_numeric($routePpmp) ? Ppmp::query()->where('ppmp_id', (int) $routePpmp)->first() : null);

        return $ppmp ? ($this->user()?->can('executive.ppmp-approval.signoff', $ppmp) ?? false) : false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:APPROVED,REJECTED,RETURNED_FOR_REVISION'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remarks' => is_string($this->input('remarks')) ? trim((string) $this->input('remarks')) : $this->input('remarks'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = strtoupper((string) $this->input('status'));
            $remarks = trim((string) $this->input('remarks', ''));

            if (in_array($status, ['RETURNED_FOR_REVISION', 'REJECTED'], true) && $remarks === '') {
                $validator->errors()->add('remarks', 'Remarks are required when returning or rejecting a PPMP.');
            }
        });
    }
}
