<?php

namespace App\Http\Requests\PublicDisclosure;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicDisclosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('public-disclosure.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // If main_file was submitted as null string or empty string, remove it so validation is clean
        if ($this->has('main_file') && ! $this->hasFile('main_file')) {
            $this->request->remove('main_file');
        }

        if ($this->has('reference_id') && ($this->input('reference_id') === '' || $this->input('reference_id') === null)) {
            $this->merge(['reference_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'max:80'],
            'reference_type' => ['nullable', 'string', 'max:150'],
            'reference_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string', 'max:255'],
            'main_file' => ['nullable', 'file', 'max:10240'],
            'procurement_document_ids' => ['nullable', 'array'],
            'procurement_document_ids.*' => ['integer', 'exists:documents,document_id'],
            'supporting_files' => ['nullable', 'array'],
            'supporting_files.*' => ['file', 'max:10240'],
            'status' => ['nullable', 'string', 'in:DRAFT,PUBLISHED'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
