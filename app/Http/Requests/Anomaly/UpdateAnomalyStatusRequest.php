<?php

namespace App\Http\Requests\Anomaly;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnomalyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('anomaly.review', $this->route('anomaly')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:IN_REVIEW,RESOLVED,DISMISSED'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
