<?php

namespace App\Http\Requests\PublicDisclosure;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicDisclosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('public-disclosure.manage') ?? false;
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
        ];
    }
}
