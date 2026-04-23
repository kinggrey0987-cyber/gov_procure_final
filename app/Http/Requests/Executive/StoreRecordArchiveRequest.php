<?php

namespace App\Http\Requests\Executive;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('executive.record-archive.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'archive_year' => ['required', 'integer', 'min:2000', 'max:'.((string) (now()->year + 1))],
            'archive_scope' => ['required', 'string', 'in:governance_yearly'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

