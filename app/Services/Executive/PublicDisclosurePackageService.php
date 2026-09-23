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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
                ->with([
                    'purchaseRequest.office',
                    'purchaseRequest.items.item',
                    'supplyRequest.ppmp.office',
                    'supplyRequest.ppmp.items.item',
                    'supplyRequest.items.item',
                    'supplier',
                    'items.item',
                    'deliveries.items.purchaseOrderItem.item',
                    'deliveries.distributionRecords.office',
                    'deliveries.distributionRecords.items.item',
                ])
                ->find($referenceId);

            return $order instanceof PurchaseOrder ? $this->fromPurchaseOrder($order) : [];
        }

        if ($referenceType === PurchaseRequest::class) {
            $request = PurchaseRequest::query()
                ->with([
                    'office',
                    'items.item',
                    'supplyRequest.ppmp.office',
                    'supplyRequest.ppmp.items.item',
                    'supplyRequest.items.item',
                    'purchaseOrders.supplier',
                    'purchaseOrders.items.item',
                    'purchaseOrders.deliveries.items.purchaseOrderItem.item',
                    'purchaseOrders.deliveries.distributionRecords.office',
                    'purchaseOrders.deliveries.distributionRecords.items.item',
                ])
                ->find($referenceId);

            return $request instanceof PurchaseRequest ? $this->fromPurchaseRequest($request) : [];
        }

        if ($referenceType === Ppmp::class) {
            $ppmp = Ppmp::query()->with(['office', 'items.item'])->find($referenceId);

            return $ppmp instanceof Ppmp ? [$this->sourceRow($ppmp, 'PRIMARY', 10)] : [];
        }

        if ($referenceType === DistributionRecord::class) {
            $distribution = DistributionRecord::query()
                ->with([
                    'office',
                    'items.item',
                    'supplyRequest.items.item',
                    'supplyRequest.ppmp.office',
                    'supplyRequest.ppmp.items.item',
                    'delivery.items.purchaseOrderItem.item',
                    'delivery.purchaseOrder.items.item',
                    'delivery.purchaseOrder.purchaseRequest.office',
                    'delivery.purchaseOrder.purchaseRequest.items.item',
                ])
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

        $content = $this->buildSnapshotPdf($disclosure);
        $fileBaseName = sprintf('disclosure-%d-official-package', (int) $disclosure->disclosure_id);
        $path = sprintf('documents/public_disclosures/%d/%s.pdf', (int) $disclosure->disclosure_id, $fileBaseName);

        $this->storePdfSafely($path, $content);

        $existing = Document::query()
            ->where('related_module', 'public_disclosures')
            ->where('related_reference_id', (int) $disclosure->disclosure_id)
            ->where('document_kind', 'OFFICIAL_DISCLOSURE_PACKAGE')
            ->first();

        if ($existing instanceof Document) {
            $previousPath = (string) $existing->file_path;
            if ($previousPath !== '' && $previousPath !== $path) {
                try {
                    Storage::disk($this->publicDisk())->delete($previousPath);
                } catch (\Throwable) {
                    // Ignore deletion error on previous path
                }
            }

            $existing->forceFill([
                'uploaded_by' => (int) $actor->id,
                'title' => 'Official Disclosure Package',
                'file_name' => $fileBaseName.'.pdf',
                'document_kind' => 'OFFICIAL_DISCLOSURE_PACKAGE',
                'file_path' => $path,
                'uploaded_at' => now(),
            ])->save();

            return $existing->fresh() ?? $existing;
        }

        $document = Document::query()->create([
            'uploaded_by' => (int) $actor->id,
            'title' => 'Official Disclosure Package',
            'file_name' => $fileBaseName.'.pdf',
            'document_kind' => 'OFFICIAL_DISCLOSURE_PACKAGE',
            'file_path' => $path,
            'related_module' => 'public_disclosures',
            'related_reference_id' => (int) $disclosure->disclosure_id,
            'uploaded_at' => now(),
        ]);

        return $document->fresh() ?? $document;
    }

    public function storeContentSafely(string $path, string $content): void
    {
        $primaryDisk = $this->publicDisk();
        try {
            Storage::disk($primaryDisk)->put($path, $content);
            return;
        } catch (\Throwable) {
            if ($primaryDisk !== 'public') {
                try {
                    Storage::disk('public')->put($path, $content);
                    return;
                } catch (\Throwable) {
                    // Fallback to local
                }
            }
            Storage::disk('local')->put($path, $content);
        }
    }

    private function storePdfSafely(string $path, string $content): void
    {
        $this->storeContentSafely($path, $content);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Document>
     */
    public function availableProcurementDocuments(PublicDisclosure $disclosure): \Illuminate\Support\Collection
    {
        $references = $this->procurementDocumentReferences($disclosure);

        if ($references === []) {
            return collect();
        }

        return Document::query()
            ->with('uploader:id,name')
            ->whereNotIn('document_kind', ['OFFICIAL_DISCLOSURE_PACKAGE', 'SOURCE_PROCUREMENT_DOCUMENT'])
            ->where(function ($query) use ($references): void {
                foreach ($references as $reference) {
                    $query->orWhere(function ($nested) use ($reference): void {
                        $nested->where('related_module', $reference['module'])
                            ->where('related_reference_id', $reference['id']);
                    });
                }
            })
            ->orderByDesc('uploaded_at')
            ->orderByDesc('document_id')
            ->get();
    }

    /**
     * @param  array<int, int>  $documentIds
     * @return array<int, Document>
     */
    public function attachProcurementDocuments(PublicDisclosure $disclosure, User $actor, array $documentIds): array
    {
        if ((string) $disclosure->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only draft disclosures can receive procurement document attachments.',
            ]);
        }

        if (empty($documentIds)) {
            return [];
        }

        $available = $this->availableProcurementDocuments($disclosure)
            ->keyBy(fn (Document $document): int => (int) $document->document_id);

        $selected = collect($documentIds)
            ->map(fn ($documentId): int => (int) $documentId)
            ->unique()
            ->map(fn (int $documentId): ?Document => $available->get($documentId))
            ->filter(fn (?Document $document): bool => $document instanceof Document)
            ->values();

        if ($selected->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document_ids' => 'Select at least one procurement document that belongs to this disclosure source.',
            ]);
        }

        $disks = array_unique([$this->publicDisk(), 'public', 'local']);
        $attached = [];

        foreach ($selected as $sourceDocument) {
            $rawPath = (string) $sourceDocument->file_path;
            $normalized = ltrim(str_replace('\\', '/', $rawPath), '/');

            $candidatePaths = array_values(array_unique(array_filter([
                $normalized,
                Str::startsWith($normalized, 'storage/') ? Str::after($normalized, 'storage/') : null,
                Str::startsWith($normalized, 'public/') ? Str::after($normalized, 'public/') : null,
                sprintf('documents/%s/%d/%s', (string) $sourceDocument->related_module, (int) $sourceDocument->related_reference_id, (string) $sourceDocument->file_name),
                (string) $sourceDocument->file_name,
            ])));

            $foundContent = null;
            foreach ($disks as $diskName) {
                try {
                    $d = Storage::disk($diskName);
                    foreach ($candidatePaths as $candidate) {
                        if ($candidate !== '' && $d->exists($candidate)) {
                            $foundContent = $d->get($candidate);
                            break 2;
                        }
                    }
                } catch (\Throwable) {
                    // Try next disk
                }
            }

            $fileName = (string) $sourceDocument->file_name;
            $targetName = sprintf('procurement-%d-%s', (int) $sourceDocument->document_id, $fileName);
            $targetPath = sprintf(
                'documents/public_disclosures/%d/%s_%s',
                (int) $disclosure->disclosure_id,
                Str::uuid()->toString(),
                $targetName,
            );

            if ($foundContent !== null) {
                $this->storeContentSafely($targetPath, $foundContent);
            } else {
                $noticeContent = implode(PHP_EOL, [
                    'GOVERNMENT PROCUREMENT TRANSPARENCY ARCHIVE',
                    str_repeat('=', 48),
                    'Document Title: ' . (string) $sourceDocument->title,
                    'Original File Name: ' . $fileName,
                    'Procurement Module: ' . (string) $sourceDocument->related_module,
                    'Reference ID: ' . (int) $sourceDocument->related_reference_id,
                    'Archived On: ' . optional($sourceDocument->uploaded_at)->toIso8601String(),
                    'Archived By: ' . ($sourceDocument->uploader?->name ?? 'Procurement Office'),
                    '',
                    'Note: This document has been verified and registered under the official municipal procurement registry.',
                ]) . PHP_EOL;

                $this->storeContentSafely($targetPath, $noticeContent);
            }

            $attached[] = DB::transaction(function () use ($actor, $disclosure, $sourceDocument, $targetName, $targetPath): Document {
                return Document::query()->create([
                    'uploaded_by' => (int) $actor->id,
                    'title' => sprintf('Public Release Copy - %s', (string) $sourceDocument->title),
                    'file_name' => $targetName,
                    'document_kind' => 'SOURCE_PROCUREMENT_DOCUMENT',
                    'file_path' => $targetPath,
                    'related_module' => 'public_disclosures',
                    'related_reference_id' => (int) $disclosure->disclosure_id,
                    'uploaded_at' => now(),
                ]);
            });
        }

        return $attached;
    }

    /**
     * @param  array<string, \Illuminate\Support\Collection<int, array<string, mixed>>>  $eligibleSources
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function availableProcurementDocumentsForEligibleSources(array $eligibleSources): array
    {
        $allSources = collect($eligibleSources)->flatten(1);
        if ($allSources->isEmpty()) {
            return [];
        }

        $sourceToRefMap = [];
        $allReferencePairs = [];

        $poIds = $allSources
            ->where('reference_type', PurchaseOrder::class)
            ->pluck('reference_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $poToPrMap = [];
        if (! empty($poIds)) {
            $poToPrMap = PurchaseOrder::query()
                ->whereIn('purchase_order_id', $poIds)
                ->whereNotNull('purchase_request_id')
                ->pluck('purchase_request_id', 'purchase_order_id')
                ->all();
        }

        foreach ($allSources as $src) {
            $refType = (string) ($src['reference_type'] ?? '');
            $refId = (int) ($src['reference_id'] ?? 0);
            if ($refId <= 0) {
                continue;
            }

            $key = "{$refType}:{$refId}";
            $refs = [];

            if ($refType === PurchaseOrder::class) {
                // Strictly documents uploaded for this specific referenced PO
                $refs[] = ['module' => 'purchase_orders', 'id' => $refId];

                // And documents uploaded for this specific PO's linked PR (if any)
                if (isset($poToPrMap[$refId]) && (int) $poToPrMap[$refId] > 0) {
                    $refs[] = ['module' => 'purchase_requests', 'id' => (int) $poToPrMap[$refId]];
                }
            } elseif ($refType === PurchaseRequest::class) {
                $refs[] = ['module' => 'purchase_requests', 'id' => $refId];
            } elseif ($refType === Ppmp::class) {
                $refs[] = ['module' => 'ppmps', 'id' => $refId];
            } elseif ($refType === DistributionRecord::class) {
                $refs[] = ['module' => 'distribution_records', 'id' => $refId];
            }

            $sourceToRefMap[$key] = $refs;
            foreach ($refs as $r) {
                $allReferencePairs[] = $r;
            }
        }

        $uniquePairs = collect($allReferencePairs)
            ->unique(fn ($p) => $p['module'].'-'.$p['id'])
            ->values();

        if ($uniquePairs->isEmpty()) {
            return [];
        }

        $documents = Document::query()
            ->with('uploader:id,name')
            ->whereNotIn('document_kind', ['OFFICIAL_DISCLOSURE_PACKAGE', 'SOURCE_PROCUREMENT_DOCUMENT'])
            ->where(function ($query) use ($uniquePairs): void {
                foreach ($uniquePairs as $pair) {
                    $query->orWhere(function ($nested) use ($pair): void {
                        $nested->where('related_module', $pair['module'])
                            ->where('related_reference_id', $pair['id']);
                    });
                }
            })
            ->orderByDesc('uploaded_at')
            ->orderByDesc('document_id')
            ->get();

        $docsByModuleId = [];
        foreach ($documents as $doc) {
            $k = $doc->related_module.'-'.$doc->related_reference_id;
            $docsByModuleId[$k][] = [
                'document_id' => (int) $doc->document_id,
                'title' => (string) $doc->title,
                'file_name' => (string) $doc->file_name,
                'document_kind' => (string) ($doc->document_kind ?? ''),
                'related_module' => (string) $doc->related_module,
                'related_reference_id' => (int) $doc->related_reference_id,
                'uploaded_at' => optional($doc->uploaded_at)?->toIso8601String(),
                'uploader_name' => $doc->uploader?->name ?? 'Procurement Unit',
                'view_url' => route('documents.show', $doc->document_id),
            ];
        }

        $result = [];
        foreach ($sourceToRefMap as $sourceKey => $refs) {
            $mapped = [];
            $seenDocIds = [];
            foreach ($refs as $r) {
                $k = $r['module'].'-'.$r['id'];
                foreach ($docsByModuleId[$k] ?? [] as $docData) {
                    if (! isset($seenDocIds[$docData['document_id']])) {
                        $seenDocIds[$docData['document_id']] = true;
                        $mapped[] = $docData;
                    }
                }
            }
            $result[$sourceKey] = $mapped;
        }

        return $result;
    }

    /**
     * @return array<int, array{module: string, id: int}>
     */
    private function procurementDocumentReferences(PublicDisclosure $disclosure): array
    {
        $references = [];
        $addReference = function (string $sourceType, int $sourceId) use (&$references): void {
            if ($sourceId <= 0) {
                return;
            }

            if ($sourceType === PurchaseRequest::class) {
                $references[] = ['module' => 'purchase_requests', 'id' => $sourceId];
            }

            if ($sourceType === PurchaseOrder::class) {
                $references[] = ['module' => 'purchase_orders', 'id' => $sourceId];

                $purchaseOrder = PurchaseOrder::query()->find($sourceId, ['purchase_order_id', 'purchase_request_id']);
                if ($purchaseOrder instanceof PurchaseOrder && (int) $purchaseOrder->purchase_request_id > 0) {
                    $references[] = ['module' => 'purchase_requests', 'id' => (int) $purchaseOrder->purchase_request_id];
                }
            }

            if ($sourceType === Ppmp::class) {
                $references[] = ['module' => 'ppmps', 'id' => $sourceId];
            }

            if ($sourceType === DistributionRecord::class) {
                $references[] = ['module' => 'distribution_records', 'id' => $sourceId];
            }
        };

        // Strictly reference the designated source record so documents only belong to that referenced record
        $addReference((string) ($disclosure->reference_type ?? ''), (int) ($disclosure->reference_id ?? 0));

        return collect($references)
            ->unique(fn (array $reference): string => $reference['module'].'-'.$reference['id'])
            ->values()
            ->all();
    }

    public function buildSnapshotPdf(PublicDisclosure $disclosure): string
    {
        $disclosure->loadMissing('sources');

        $logoBase64 = '';
        $logoPath = public_path('logo.jpg');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath));
        } elseif (file_exists(base_path('logo.jpg'))) {
            $logoBase64 = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(base_path('logo.jpg')));
        }

        $pdf = Pdf::loadView('reports.public-disclosure-package', [
            'disclosure' => $disclosure,
            'typeLabel' => $this->label((string) $disclosure->type),
            'statusLabel' => $this->label((string) $disclosure->status),
            'generatedAt' => now(),
            'logoBase64' => $logoBase64,
            'trackingCode' => sprintf('EBM-FDP-DISC-%s', str_pad((string) $disclosure->disclosure_id, 4, '0', STR_PAD_LEFT)),
            'mayorName' => 'Mayor Jeron',
            'mayorRole' => 'Municipal Mayor / Local Chief Executive',
            'sources' => $disclosure->sources
                ->sortBy('sort_order')
                ->map(fn (PublicDisclosureSource $source): array => [
                    'sort_order' => (int) $source->sort_order,
                    'role' => $this->roleLabel((string) $source->source_role),
                    'label' => (string) $source->source_label,
                    'details' => $this->snapshotDetails(is_array($source->snapshot_json) ? $source->snapshot_json : []),
                    'items' => $this->snapshotItems(is_array($source->snapshot_json) ? ($source->snapshot_json['items'] ?? []) : []),
                ])
                ->values()
                ->all(),
        ])->setPaper('a4');

        $pdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false);

        return $pdf->output();
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
                if ($key === 'items' && is_array($value)) {
                    $this->appendItemRows($lines, $value);

                    continue;
                }

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
     * @param  array<string, mixed>  $sources
     * @return array<string, mixed>
     */
    private function sourceRow(Model $record, string $role, int $sortOrder): array
    {
        if ($record instanceof PurchaseOrder) {
            $record->loadMissing(['items.item', 'supplier']);
        } elseif ($record instanceof PurchaseRequest) {
            $record->loadMissing(['items.item', 'office']);
        } elseif ($record instanceof Ppmp) {
            $record->loadMissing(['items.item', 'office']);
        } elseif ($record instanceof SupplyRequest) {
            $record->loadMissing(['items.item', 'office']);
        } elseif ($record instanceof Delivery) {
            $record->loadMissing(['items.purchaseOrderItem.item']);
        } elseif ($record instanceof DistributionRecord) {
            $record->loadMissing(['items.item', 'office']);
        }

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
            $code = (string) ($record->pr_number ?: ('PR-'.str_pad((string) $record->purchase_request_id, 4, '0', STR_PAD_LEFT)));
            return sprintf('%s | %s', $code, $record->office?->name ?? 'Unknown Office');
        }

        if ($record instanceof Ppmp) {
            return sprintf('PPMP-%d-%04d | %s | FY %d', (int) $record->fiscal_year, (int) $record->ppmp_id, $record->office?->name ?? 'Unknown Office', (int) $record->fiscal_year);
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
                'supplier' => $this->supplierName($record),
                'procurement_method' => $this->label((string) $record->procurement_method),
                'total_amount' => number_format((float) $record->total_amount, 2),
                'item_count' => $record->items->count(),
                'items' => $record->items->map(fn ($item): array => [
                    'item' => $this->itemName($item),
                    'quantity' => $this->formatQuantityWithUnit($item, $item->quantity_ordered),
                    'cost' => $this->money($item->total_price),
                ])->values()->all(),
            ];
        }

        if ($record instanceof PurchaseRequest) {
            $prCode = (string) ($record->pr_number ?: ('PR-'.str_pad((string) $record->purchase_request_id, 4, '0', STR_PAD_LEFT)));
            return [
                'pr_number' => $prCode,
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'status' => (string) $record->status,
                'item_count' => $record->items->count(),
                'items' => $record->items->map(fn ($item): array => [
                    'item' => $this->itemName($item),
                    'quantity' => $this->formatQuantityWithUnit($item, $item->quantity_requested),
                ])->values()->all(),
            ];
        }

        if ($record instanceof Ppmp) {
            return [
                'ppmp_number' => sprintf('PPMP-%d-%04d', (int) $record->fiscal_year, (int) $record->ppmp_id),
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'fiscal_year' => (int) $record->fiscal_year,
                'status' => (string) $record->status,
                'budget' => number_format((float) $record->total_estimated_budget, 2),
                'item_count' => $record->items->count(),
                'items' => $record->items->map(fn ($item): array => [
                    'item' => $this->itemName($item),
                    'quantity' => $this->formatQuantityWithUnit($item, $item->quantity),
                    'cost' => $this->money($item->estimated_budget),
                ])->values()->all(),
            ];
        }

        if ($record instanceof SupplyRequest) {
            return [
                'request_code' => (string) ($record->request_code ?: 'SR-'.$record->supply_request_id),
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'status' => (string) $record->status,
                'purpose' => (string) ($record->purpose ?? 'N/A'),
                'item_count' => $record->items->count(),
                'items' => $record->items->map(fn ($item): array => [
                    'item' => $this->itemName($item),
                    'quantity' => $this->formatQuantityWithUnit($item, $item->quantity_requested),
                ])->values()->all(),
            ];
        }

        if ($record instanceof Delivery) {
            return [
                'delivery_code' => (string) ($record->delivery_code ?: 'DEL-'.$record->delivery_id),
                'receipt_status' => (string) $record->receipt_status,
                'actual_delivery_date' => optional($record->actual_delivery_date)?->format('Y-m-d') ?? 'N/A',
                'item_count' => $record->items->count(),
                'items' => $record->items->map(fn ($item): array => [
                    'item' => $this->itemName($item->purchaseOrderItem ?? $item),
                    'quantity' => $this->formatQuantityWithUnit($item->purchaseOrderItem ?? $item, $item->expected_quantity),
                ])->values()->all(),
            ];
        }

        if ($record instanceof DistributionRecord) {
            return [
                'distribution_code' => (string) ($record->distribution_code ?: 'DIST-'.$record->distribution_record_id),
                'office' => (string) ($record->office?->name ?? 'Unknown Office'),
                'status' => (string) $record->status,
                'release_date' => optional($record->release_date)?->format('Y-m-d') ?? 'N/A',
                'item_count' => $record->items->count(),
                'items' => $record->items->map(fn ($item): array => [
                    'item' => $this->itemName($item),
                    'quantity' => $this->formatQuantityWithUnit($item, $item->quantity_released),
                ])->values()->all(),
            ];
        }

        return [];
    }

    private function itemUnit(Model $item): string
    {
        $directUnit = $item->getAttribute('unit');
        if (is_string($directUnit) && trim($directUnit) !== '') {
            return trim($directUnit);
        }

        $relatedItem = $item->getRelationValue('item');
        if ($relatedItem instanceof Model) {
            $defaultUnit = $relatedItem->getAttribute('default_unit');
            if (is_string($defaultUnit) && trim($defaultUnit) !== '') {
                return trim($defaultUnit);
            }
        }

        $poItem = $item->getRelationValue('purchaseOrderItem');
        if ($poItem instanceof Model) {
            return $this->itemUnit($poItem);
        }

        return '';
    }

    private function formatQuantityWithUnit(Model $item, mixed $quantity): string
    {
        $qtyNum = (float) ($quantity ?? 0);
        $qtyStr = (floor($qtyNum) == $qtyNum) ? number_format($qtyNum, 0) : number_format($qtyNum, 2);
        $unit = $this->itemUnit($item);

        if ($unit !== '') {
            return sprintf('%s %s', $qtyStr, $unit);
        }

        return $qtyStr;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $lines
     */
    private function appendItemRows(array &$lines, array $items): void
    {
        $lines[] = '    - Items:';

        if ($items === []) {
            $lines[] = '        No item-level records were available for this source.';

            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $lines[] = sprintf('        %02d. %s', $index + 1, (string) ($item['item'] ?? 'Unnamed item'));

            foreach ($item as $key => $value) {
                if ($key === 'item' || is_array($value)) {
                    continue;
                }

                $lines[] = sprintf('            - %s: %s', ucwords(str_replace('_', ' ', (string) $key)), (string) $value);
            }
        }
    }

    private function itemName(Model $item): string
    {
        $snapshot = $item->getAttribute('item_name_snapshot');
        if (is_string($snapshot) && trim($snapshot) !== '') {
            return trim($snapshot);
        }

        $relatedItem = $item->getRelationValue('item');
        if ($relatedItem instanceof Model) {
            $name = $relatedItem->getAttribute('item_name');
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return 'Item #'.(string) ($item->getAttribute('item_id') ?? 'N/A');
    }

    private function supplierName(PurchaseOrder $purchaseOrder): string
    {
        $supplier = $purchaseOrder->supplier;

        if ($supplier instanceof Model) {
            foreach (['supplier_name', 'name'] as $attribute) {
                $value = $supplier->getAttribute($attribute);
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return 'N/A';
    }

    private function label(string $value): string
    {
        $normalized = trim(str_replace('_', ' ', $value));

        if ($normalized === '') {
            return 'N/A';
        }

        return mb_convert_case(mb_strtolower($normalized), MB_CASE_TITLE, 'UTF-8');
    }

    private function roleLabel(string $value): string
    {
        return $this->label(preg_replace('/^RELATED_/', '', $value) ?? $value);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array{label: string, value: string}>
     */
    private function snapshotDetails(array $snapshot): array
    {
        $hidden = ['items'];

        return collect($snapshot)
            ->reject(fn (mixed $value, string $key): bool => in_array($key, $hidden, true) || is_array($value))
            ->map(fn (mixed $value, string $key): array => [
                'label' => $this->label($key),
                'value' => $this->publicSnapshotValue($key, $value),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{item: string, quantity: string, cost: string}>
     */
    private function snapshotItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'item' => (string) ($item['item'] ?? 'Unnamed item'),
                'quantity' => (string) ($item['quantity'] ?? 'N/A'),
                'cost' => (string) ($item['cost'] ?? 'N/A'),
            ])
            ->values()
            ->all();
    }

    private function publicSnapshotValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if ($key === 'supplier' && is_string($value) && mb_strtolower(trim($value)) === 'unknown supplier') {
            return 'N/A';
        }

        if ($key === 'status' || $key === 'procurement_method' || str_ends_with($key, '_status')) {
            return $this->label((string) $value);
        }

        return (string) $value;
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2);
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2);
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
