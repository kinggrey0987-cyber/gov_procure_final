<?php

namespace App\Http\Requests\PublicDisclosure;

use Illuminate\Foundation\Http\FormRequest;

class AttachProcurementDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('public-disclosure.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'distinct', 'exists:documents,document_id'],
        ];
    }
}
