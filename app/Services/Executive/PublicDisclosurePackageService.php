<?php

namespace App\Services\Executive;

use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Document;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PublicDisclosureSource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PublicDisclosurePackageService
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildSourcePackage(?string $referenceType, ?int $referenceId): array
    {
        if (! $referenceType || ! $referenceId) {
            return [];
        }

        if ($referenceType === PurchaseOrder::class) {
            $order = PurchaseOrder::query()
                ->with(['purchaseRequest.office', 'supplyRequest.ppmp.office', 'supplier', 'items', 'deliveries.distributionRecords.office'])
                ->find($referenceId);

            return $order instanceof PurchaseOrder ? $this->fromPurchaseOrder($order) : [];
        }

        if ($referenceType === PurchaseRequest::class) {
            $request = PurchaseRequest::query()
                ->with(['office', 'supplyRequest.ppmp.office', 'purchaseOrders.supplier', 'purchaseOrders.items', 'purchaseOrders.deliveries.distributionRecords.office'])
                ->find($referenceId);

            return $request instanceof PurchaseRequest ? $this->fromPurchaseRequest($request) : [];
        }

        if ($referenceType === Ppmp::class) {
            $ppmp = Ppmp::query()->with(['office', 'items'])->find($referenceId);

            return $ppmp instanceof Ppmp ? [$this->sourceRow($ppmp, 'PRIMARY', 10)] : [];
        }

        if ($referenceType === DistributionRecord::class) {
            $distribution = DistributionRecord::query()
                ->with(['office', 'supplyRequest.ppmp.office', 'delivery.purchaseOrder.purchaseRequest.office'])
                ->find($referenceId);

            return $distribution instanceof DistributionRecord ? $this->fromDistribution($distribution) : [];
        }

        return [];
    }

    public function syncSources(PublicDisclosure $disclosure): void
    {
        $sources = $this->buildSourcePackage(
            is_string($disclosure->reference_type) ? $disclosure->reference_type : null,
            $disclosure->reference_id ? (int) $disclosure->reference_id : null,
        );

        if ($sources === []) {
            PublicDisclosureSource::query()->updateOrCreate(
                [
                    'disclosure_id' => (int) $disclosure->disclosure_id,
                    'source_type' => (string) ($disclosure->reference_type ?? PublicDisclosure::class),
                    'source_id' => (int) ($disclosure->reference_id ?? $disclosure->disclosure_id),
                    'source_role' => 'PRIMARY',
                ],
                [
                    'source_label' => 'Disclosure record #'.(int) $disclosure->disclosure_id,
                    'sort_order' => 10,
                    'snapshot_json' => [
                        'title' => (string) $disclosure->title,
                        'type' => (string) $disclosure->type,
                        'status' => (string) $disclosure->status,
                    ],
                ],
            );

            return;
        }

        $seen = [];
        foreach ($sources as $source) {
            $key = implode('|', [
                (string) $source['source_type'],
                (string) $source['source_id'],
                (string) $source['source_role'],
            ]);
            $seen[] = $key;

            PublicDisclosureSource::query()->updateOrCreate(
                [
                    'disclosure_id' => (int) $disclosure->disclosure_id,
                    'source_type' => (string) $source['source_type'],
                    'source_id' => (int) $source['source_id'],
                    'source_role' => (string) $source['source_role'],
                ],
                [
                    'source_label' => (string) $source['source_label'],
                    'sort_order' => (int) $source['sort_order'],
                    'snapshot_json' => $source['snapshot_json'] ?? [],
                ],
            );
        }

        PublicDisclosureSource::query()
            ->where('disclosure_id', (int) $disclosure->disclosure_id)
            ->get()
            ->each(function (PublicDisclosureSource $source) use ($seen): void {
                $key = implode('|', [
                    (string) $source->source_type,
                    (string) $source->source_id,
                    (string) $source->source_role,
                ]);

                if (! in_array($key, $seen, true)) {
                    $source->delete();
                }
            });
    }

    public function ensureOfficialPackageAttachment(PublicDisclosure $disclosure, User $actor): Document
    {
        $this->syncSources($disclosure);
        $disclosure->loadMissing('sources');

        $content = $this->buildSnapshotContent($disclosure);
        $fileBaseName = sprintf('disclosure-%d-official-package', (int) $disclosure->disclosure_id);

        $existing = Document::query()
            ->where('related_module', 'public_disclosures')
            ->where('related_reference_id', (int) $disclosure->disclosure_id)
            ->where('document_kind', 'OFFICIAL_DISCLOSURE_PACKAGE')
            ->first();

        if ($existing instanceof Document) {
            $path = (string) $existing->file_path;
            if ($path === '') {
                $path = sprintf('documents/public_disclosures/%d/%s.txt', (int) $disclosure->disclosure_id, $fileBaseName);
            }

            Storage::disk($this->publicDisk())->put($path, $content);
            $existing->forceFill([
                'uploaded_by' => (int) $actor->id,
                'title' => 'Official Disclosure Package',
                'file_name' => basename($path),
                'document_kind' => 'OFFICIAL_DISCLOSURE_PACKAGE',
                'file_path' => $path,
                'uploaded_at' => now(),
            ])->save();

            return $existing->fresh() ?? $existing;
        }

        $document = $this->documentService->createGeneratedAttachment(
            actor: $actor,
            relatedModule: 'public_disclosures',
            relatedReferenceId: (int) $disclosure->disclosure_id,
            title: 'Official Disclosure Package',
            fileName: $fileBaseName,
            content: $content,
            mimeExtension: 'txt',
        );

        $document->forceFill(['document_kind' => 'OFFICIAL_DISCLOSURE_PACKAGE'])->save();

        return $document->fresh() ?? $document;
    }

    public function buildSnapshotContent(PublicDisclosure $disclosure): string
    {
        $disclosure->loadMissing('sources');

        $lines = [
            'OFFICIAL PUBLIC DISCLOSURE PACKAGE',
            str_repeat('=', 42),
            sprintf('Disclosure ID: %d', (int) $disclosure->disclosure_id),
            sprintf('Title: %s', (string) $disclosure->title),
            sprintf('Type: %s', (string) $disclosure->type),
            sprintf('Status: %s', (string) $disclosure->status),
            sprintf('Published At: %s', optional($disclosure->published_at)?->toIso8601String() ?? 'Not published yet'),
            sprintf('Generated At: %s', now()->toIso8601String()),
            '',
            'PUBLIC SUMMARY',
            str_repeat('-', 14),
            (string) ($disclosure->description ?: 'No public summary was provided.'),
            '',
            'PROCUREMENT TRACEABILITY',
            str_repeat('-', 25),
        ];

        foreach ($disclosure->sources as $source) {
            $lines[] = sprintf('%02d. [%s] %s', (int) $source->sort_order, (string) $source->source_role, (string) $source->source_label);
            $snapshot = is_array($source->snapshot_json) ? $source->snapshot_json : [];
            foreach ($snapshot as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $lines[] = sprintf('    - %s: %s', ucwords(str_replace('_', ' ', (string) $key)), (string) $value);
            }
        }

        $lines[] = '';
        $lines[] = 'NOTE';
        $lines[] = str_repeat('-', 4);
        $lines[] = 'This file is a frozen public-safe package generated from approved source records at publication time.';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fromPurchaseOrder(PurchaseOrder $order): array
    {
        $sources = [$this->sourceRow($order, 'PRIMARY', 30)];

        if ($order->purchaseRequest instanceof PurchaseRequest) {
            $sources[] = $this->sourceRow($order->purchaseRequest, 'RELATED_PR', 20);
        }

        if ($order->supplyRequest instanceof SupplyRequest) {
            $sources[] = $this->sourceRow($order->supplyRequest, 'RELATED_SUPPLY_REQUEST', 15);
            if ($order->supplyRequest->ppmp instanceof Ppmp) {
                $sources[] = $this->sourceRow($order->supplyRequest->ppmp, 'RELATED_PPMP', 10);
            }
        }

        foreach ($order->deliveries as $index => $delivery) {
            $sources[] = $this->sourceRow($delivery, 'RELATED_DELIVERY', 40 + $index);
            foreach ($delivery->distributionRecords as $distributionIndex => $distribution) {
                $sources[] = $this->sourceRow($distribution, 'RELATED_DISTRIBUTION', 60 + $distributionIndex);
            }
        }

        return $this->uniqueSources($sources);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fromPurchaseRequest(PurchaseRequest $request): array
    {
        $sources = [$this->sourceRow($request, 'PRIMARY', 20)];

        if ($request->supplyRequest instanceof SupplyRequest) {
            $sources[] = $this->sourceRow($request->supplyRequest, 'RELATED_SUPPLY_REQUEST', 15);
            if ($request->supplyRequest->ppmp instanceof Ppmp) {
                $sources[] = $this->sourceRow($request->supplyRequest->ppmp, 'RELATED_PPMP', 10);
            }
        }

        foreach ($request->purchaseOrders as $index => $order) {
            $sources[] = $this->sourceRow($order, 'RELATED_PO', 30 + $index);
            foreach ($order->deliveries as $deliveryIndex => $delivery) {
                $sources[] = $this->sourceRow($delivery, 'RELATED_DELIVERY', 50 + $deliveryIndex);
            }
        }

        return $this->uniqueSources($sources);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fromDistribution(DistributionRecord $distribution): array
    {
        $sources = [$this->sourceRow($distribution, 'PRIMARY', 60)];

        if ($distribution->supplyRequest instanceof SupplyRequest) {
            $sources[] = $this->sourceRow($distribution->supplyRequest, 'RELATED_SUPPLY_REQUEST', 15);
            if ($distribution->supplyRequest->ppmp instanceof Ppmp) {
                $sources[] = $this->sourceRow($distribution->supplyRequest->ppmp, 'RELATED_PPMP', 10);
            }
        }

        if ($distribution->delivery instanceof Delivery) {
            $sources[] = $this->sourceRow($distribution->delivery, 'RELATED_DELIVERY', 50);
            if ($distribution->delivery->purchaseOrder instanceof PurchaseOrder) {
                $sources[] = $this->sourceRow($distribution->delivery->purchaseOrder, 'RELATED_PO', 30);
                if ($distribution->delivery->purchaseOrder->purchaseRequest instanceof PurchaseRequest) {
                    $sources[] = $this->sourceRow($distribution->delivery->purchaseOrder->purchaseRequest, 'RELATED_PR', 20);
                }
            }
        }

        return $this->uniqueSources($sources);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceRow(Model $record, string $role, int $sortOrder): array
    {
        return [
            'source_type' => $record::class,
            'source_id' => (int) $record->getKey(),
            'source_role' => $role,
            'source_label' => $this->sourceLabel($record),
            'sort_order' => $sortOrder,
            'snapshot_json' => $this->snapshot($record),
        ];
    }

    private function sourceLabel(Model $record): string
    {
        if ($record instanceof PurchaseOrder) {
            return sprintf('PO %s', (string) ($record->po_number ?: '#'.$record->purchase_order_id));
        }

        if ($record instanceof PurchaseRequest) {
            return sprintf('PR-%d | %s', (int) $record->purchase_request_id, $record->office?->name ?? 'Unknown Office');
        }

        if ($record instanceof Ppmp) {
            return sprintf('PPMP-%d | %s | FY %d', (int) $record->ppmp_id, $record->office?->name ?? 'Unknown Office', (int) $record->fiscal_year);
        }

        if ($record instanceof SupplyRequest) {
            return sprintf('%s | %s', (string) ($record->request_code ?: 'Supply Request #'.$record->supply_request_id), $record->office?->name ?? 'Unknown Office');
        }

        if ($record instanceof Delivery) {
            return (string) ($record->delivery_code ?: 'Delivery #'.$record->delivery_id);
        }

        if ($record instanceof DistributionRecord) {
            return sprintf('%s | %s', (string) ($record->distribution_code ?: 'Distribution #'.$record->distribution_record_id), $record->office?->name ?? 'Unknown Office');
        }

        return class_basename($record).' #'.$record->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $record): array
    {
        if ($record instanceof PurchaseOrder) {
            return [
                'po_number' => (string) ($record->po_number ?: 'PO-'.$record->purchase_order_id),
                'status' => (string) $record->status,
                'supplier' => (string) ($record->supplier?->name ?? 'Unknown Supplier'),
                'procurement_method' => (string) $record->procurement_method,
                'total_amount' => number_format((float) $record->total_amount, 2),
                'item_count' => $record->items->count(),
            ];
        }

        if ($record instanceof PurchaseRequest) {
            return [
                'pr_number' => 'PR-'.(int) $record->purchase_request_id,
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'status' => (string) $record->status,
            ];
        }

        if ($record instanceof Ppmp) {
            return [
                'ppmp_number' => 'PPMP-'.(int) $record->ppmp_id,
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'fiscal_year' => (int) $record->fiscal_year,
                'status' => (string) $record->status,
                'budget' => number_format((float) $record->total_estimated_budget, 2),
            ];
        }

        if ($record instanceof SupplyRequest) {
            return [
                'request_code' => (string) ($record->request_code ?: 'SR-'.$record->supply_request_id),
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'status' => (string) $record->status,
                'purpose' => (string) ($record->purpose ?? 'N/A'),
            ];
        }

        if ($record instanceof Delivery) {
            return [
                'delivery_code' => (string) ($record->delivery_code ?: 'DEL-'.$record->delivery_id),
                'receipt_status' => (string) $record->receipt_status,
                'actual_delivery_date' => optional($record->actual_delivery_date)?->format('Y-m-d') ?? 'N/A',
            ];
        }

        if ($record instanceof DistributionRecord) {
            return [
                'distribution_code' => (string) ($record->distribution_code ?: 'DIST-'.$record->distribution_record_id),
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'status' => (string) $record->status,
                'release_date' => optional($record->release_date)?->format('Y-m-d') ?? 'N/A',
            ];
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, array<string, mixed>>
     */
    private function uniqueSources(array $sources): array
    {
        $unique = [];
        foreach ($sources as $source) {
            $key = (string) $source['source_type'].'|'.(int) $source['source_id'].'|'.(string) $source['source_role'];
            $unique[$key] = $source;
        }

        usort($unique, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));

        return array_values($unique);
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }
}
