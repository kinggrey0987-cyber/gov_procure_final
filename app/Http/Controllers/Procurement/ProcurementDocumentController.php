<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementDocumentController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PROCUREMENT_MODULES = ['purchase_requests', 'purchase_orders'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.documents.view-any'), 403);

        $module = strtolower(trim((string) $request->input('module', '')));
        $search = trim((string) $request->input('search', ''));
        $relatedModule = strtolower(trim((string) $request->input('related_module', '')));
        $relatedReferenceId = (int) $request->input('related_reference_id', 0);

        return Inertia::render('Procurement/Documents/Index', [
            'documents' => $this->paginatedDocuments($module, $search),
            'filters' => [
                'module' => in_array($module, self::PROCUREMENT_MODULES, true) ? $module : '',
                'search' => $search,
            ],
            'scopeModules' => self::PROCUREMENT_MODULES,
            'relatedRecordOptions' => $this->relatedRecordOptions(),
            'uploadEnabled' => true,
            'uploadDefaults' => [
                'related_module' => in_array($relatedModule, self::PROCUREMENT_MODULES, true) ? $relatedModule : '',
                'related_reference_id' => $relatedReferenceId > 0 ? $relatedReferenceId : null,
            ],
        ]);
    }

    private function paginatedDocuments(string $module, string $search)
    {
        return Document::query()
            ->with('uploader:id,name')
            ->whereIn('related_module', self::PROCUREMENT_MODULES)
            ->whereNotIn('document_kind', ['OFFICIAL_DISCLOSURE_PACKAGE', 'SOURCE_PROCUREMENT_DOCUMENT'])
            ->when(in_array($module, self::PROCUREMENT_MODULES, true), fn ($query) => $query->where('related_module', $module))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', '%'.$search.'%')
                        ->orWhere('file_name', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('uploaded_at')
            ->orderByDesc('document_id')
            ->paginate(5)
            ->withQueryString()
            ->through(fn (Document $document): array => $this->mappedDocumentRow($document));
    }

    private function mappedDocumentRow(Document $document): array
    {
        return [
            'document_id' => (int) $document->document_id,
            'title' => (string) $document->title,
            'file_name' => (string) $document->file_name,
            'document_kind' => (string) ($document->document_kind ?? ''),
            'related_module' => (string) $document->related_module,
            'related_reference_id' => (int) $document->related_reference_id,
            'related_reference_label' => $this->resolveReferenceLabel($document),
            'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
            'uploader_name' => $document->uploader?->name,
        ];
    }

    private function relatedRecordOptions(): array
    {
        return [
            ...$this->purchaseRequestOptions(),
            ...$this->purchaseOrderOptions(),
        ];
    }

    private function purchaseRequestOptions()
    {
        return PurchaseRequest::query()
            ->with('office:id,name')
            ->latest('purchase_request_id')
            ->limit(200)
            ->get(['purchase_request_id', 'office_id', 'status'])
            ->map(fn (PurchaseRequest $purchaseRequest): array => [
                'related_module' => 'purchase_requests',
                'related_reference_id' => (int) $purchaseRequest->purchase_request_id,
                'label' => sprintf(
                    '%s - %s (%s)',
                    $this->formatPurchaseRequestReference($purchaseRequest),
                    (string) ($purchaseRequest->office?->name ?? 'Unknown Office'),
                    (string) $purchaseRequest->status
                ),
            ])
            ->values()
            ->all();
    }

    private function purchaseOrderOptions()
    {
        return PurchaseOrder::query()
            ->with('purchaseRequest.office:id,name')
            ->latest('purchase_order_id')
            ->limit(200)
            ->get(['purchase_order_id', 'purchase_request_id', 'po_number', 'status'])
            ->map(fn (PurchaseOrder $purchaseOrder): array => [
                'related_module' => 'purchase_orders',
                'related_reference_id' => (int) $purchaseOrder->purchase_order_id,
                'label' => sprintf(
                    '%s - %s (%s)',
                    $this->formatPurchaseOrderReference($purchaseOrder),
                    (string) ($purchaseOrder->purchaseRequest?->office?->name ?? 'Unknown Office'),
                    (string) $purchaseOrder->status
                ),
            ])
            ->values()
            ->all();
    }

    private function resolveReferenceLabel(Document $document): ?string
    {
        if ((string) $document->related_module === 'purchase_requests') {
            $purchaseRequest = PurchaseRequest::query()
                ->with('office:id,name')
                ->find((int) $document->related_reference_id, ['purchase_request_id', 'office_id', 'status']);

            if ($purchaseRequest instanceof PurchaseRequest) {
                return sprintf(
                    '%s - %s (%s)',
                    $this->formatPurchaseRequestReference($purchaseRequest),
                    (string) ($purchaseRequest->office?->name ?? 'Unknown Office'),
                    (string) $purchaseRequest->status
                );
            }
        }

        if ((string) $document->related_module === 'purchase_orders') {
            $purchaseOrder = PurchaseOrder::query()
                ->with('purchaseRequest.office:id,name')
                ->find((int) $document->related_reference_id, ['purchase_order_id', 'purchase_request_id', 'po_number', 'status']);

            if ($purchaseOrder instanceof PurchaseOrder) {
                return sprintf(
                    '%s - %s (%s)',
                    $this->formatPurchaseOrderReference($purchaseOrder),
                    (string) ($purchaseOrder->purchaseRequest?->office?->name ?? 'Unknown Office'),
                    (string) $purchaseOrder->status
                );
            }
        }

        return null;
    }

    private function formatPurchaseRequestReference(PurchaseRequest $purchaseRequest): string
    {
        return 'PR-'.str_pad((string) $purchaseRequest->purchase_request_id, 6, '0', STR_PAD_LEFT);
    }

    private function formatPurchaseOrderReference(PurchaseOrder $purchaseOrder): string
    {
        $poNumber = trim((string) $purchaseOrder->po_number);

        if ($poNumber !== '') {
            return $poNumber;
        }

        return 'PO-'.str_pad((string) $purchaseOrder->purchase_order_id, 6, '0', STR_PAD_LEFT);
    }
}
