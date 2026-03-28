<?php

namespace App\Http\Requests\PublicDisclosure;

use Illuminate\Foundation\Http\FormRequest;

class PublishPublicDisclosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('public-disclosure.publish', $this->route('public_disclosure')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string'],
        ];
    }
}
