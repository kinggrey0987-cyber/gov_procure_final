<?php

namespace App\Http\Requests\Document;

use App\Models\PurchaseOrder;
use App\Services\Documents\DocumentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    private const PROCUREMENT_DOCUMENT_KINDS = [
        'procurement_record',
        'supplier_quotation',
        'canvass_sheet',
        'noa',
        'signed_po',
        'other_supporting_doc',
    ];

    private const ALLOWED_DOCUMENT_KINDS = [
        'procurement_record',
        'supplier_quotation',
        'canvass_sheet',
        'noa',
        'signed_po',
        'other_supporting_doc',
        'delivery_receipt',
        'sales_invoice',
        'verification_evidence',
    ];

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
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'titles' => ['sometimes', 'nullable', 'array'],
            'titles.*' => ['nullable', 'string', 'max:200'],
            'document_kind' => ['sometimes', 'nullable', 'string', Rule::in(self::ALLOWED_DOCUMENT_KINDS)],
            'document_kinds' => ['sometimes', 'nullable', 'array'],
            'document_kinds.*' => ['nullable', 'string', Rule::in(self::ALLOWED_DOCUMENT_KINDS)],
            'file' => [
                'sometimes',
                'nullable',
                'file',
                'max:15360',
                'mimes:pdf,doc,docx,csv,png,jpg,jpeg,webp',
            ],
            'files' => ['sometimes', 'nullable', 'array'],
            'files.*' => [
                'file',
                'max:15360',
                'mimes:pdf,doc,docx,csv,png,jpg,jpeg,webp',
            ],
            'related_module' => ['sometimes', 'string', Rule::in($accessService->allowedModules())],
            'related_reference_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasFile = $this->hasFile('file');
            $hasFiles = $this->hasFile('files');

            if (! $hasFile && ! $hasFiles) {
                $validator->errors()->add('file', 'Please select at least one document file to upload.');

                return;
            }

            $module = strtolower(trim((string) $this->route('related_module')));

            if (! in_array($module, ['purchase_requests', 'purchase_orders'], true)) {
                return;
            }

            if ($module === 'purchase_requests') {
                $validator->errors()->add(
                    'related_module',
                    'Procurement supporting documents must be attached to placed or finished purchase orders, not purchase requests.'
                );

                return;
            }

            if ($module === 'purchase_orders') {
                $refId = (int) ($this->route('related_reference_id') ?? $this->input('related_reference_id'));
                if ($refId > 0) {
                    $purchaseOrder = PurchaseOrder::query()->find($refId);
                    if ($purchaseOrder && ! $purchaseOrder->isFinished()) {
                        $validator->errors()->add(
                            'related_reference_id',
                            'Documents can only be uploaded for placed or finished purchase orders.'
                        );

                        return;
                    }
                }
            }

            $documentKind = strtolower(trim((string) $this->input('document_kind')));
            if ($documentKind !== '' && ! in_array($documentKind, self::PROCUREMENT_DOCUMENT_KINDS, true)) {
                $validator->errors()->add('document_kind', 'Select a valid procurement document type for this purchase order.');

                return;
            }

            $blockedKinds = [
                'delivery_receipt',
                'inspection_report',
                'verification_form',
                'distribution_record',
                'handover_form',
                'turnover_form',
            ];

            if (in_array($documentKind, $blockedKinds, true)) {
                $validator->errors()->add(
                    'document_kind',
                    'Delivery, inspection, and turnover documents must be uploaded in the delivery workflow, not under procurement documents.'
                );

                return;
            }

            // Check individual document kinds if provided in batch
            $batchKinds = (array) $this->input('document_kinds', []);
            foreach ($batchKinds as $bKind) {
                $cleanKind = strtolower(trim((string) $bKind));
                if ($cleanKind !== '' && in_array($cleanKind, $blockedKinds, true)) {
                    $validator->errors()->add(
                        'document_kinds',
                        'Delivery, inspection, and turnover documents must be uploaded in the delivery workflow, not under procurement documents.'
                    );
                    break;
                }
            }

            $title = strtolower(trim((string) $this->input('title')));
            $allFileNames = [];
            if ($hasFile) {
                $allFileNames[] = (string) $this->file('file')?->getClientOriginalName();
            }
            if ($hasFiles) {
                foreach ((array) $this->file('files') as $f) {
                    if ($f instanceof \Illuminate\Http\UploadedFile) {
                        $allFileNames[] = $f->getClientOriginalName();
                    }
                }
            }

            $haystack = strtolower($title.' '.implode(' ', $allFileNames));
            foreach (['delivery', 'receipt', 'inspection', 'verify', 'verification', 'distribution', 'handover', 'turnover'] as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $validator->errors()->add(
                        'file',
                        'Delivery, inspection, and turnover documents must be uploaded in the delivery workflow, not under procurement documents.'
                    );

                    break;
                }
            }
        });
    }
}
