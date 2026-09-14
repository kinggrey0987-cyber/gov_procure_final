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
            'title' => ['required', 'string', 'max:200'],
            'document_kind' => ['sometimes', 'nullable', 'string', Rule::in(self::ALLOWED_DOCUMENT_KINDS)],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,csv,png,jpg,jpeg,webp',
            ],
            'related_module' => ['sometimes', 'string', Rule::in($accessService->allowedModules())],
            'related_reference_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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

            $title = strtolower(trim((string) $this->input('title')));
            $fileName = strtolower((string) $this->file('file')?->getClientOriginalName());
            $haystack = $title.' '.$fileName;

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
