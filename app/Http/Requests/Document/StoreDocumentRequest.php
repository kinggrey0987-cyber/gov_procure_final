<?php

namespace App\Http\Requests\Document;

use App\Services\Documents\DocumentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $accessService = app(DocumentAccessService::class);
        $module = (string) $this->route('related_module');

        return (bool) $this->user()
            && in_array($accessService->normalizeModule($module), $accessService->allowedModules(), true);
    }

    public function rules(): array
    {
        $accessService = app(DocumentAccessService::class);

        return [
            'title' => ['required', 'string', 'max:200'],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg',
            ],
            'related_module' => ['sometimes', 'string', Rule::in($accessService->allowedModules())],
            'related_reference_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
