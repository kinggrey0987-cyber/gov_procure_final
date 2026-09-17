<?php

namespace App\Http\Controllers\Executive;

use App\Http\Requests\PublicDisclosure\AttachProcurementDocumentsRequest;
use App\Http\Requests\PublicDisclosure\PublishPublicDisclosureRequest;
use App\Http\Requests\PublicDisclosure\StorePublicDisclosureRequest;
use App\Http\Requests\PublicDisclosure\UpdatePublicDisclosureRequest;
use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\Analytics\PublicDisclosureService;
use App\Services\Documents\DocumentService;
use App\Services\Executive\DisclosureEligibilityService;
use App\Services\Executive\PublicDisclosurePackageService;
use App\Services\History\HistoryTimelineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicDisclosureController
{
    public function __construct(
        private readonly PublicDisclosureService $disclosureService,
        private readonly HistoryTimelineService $historyTimelineService,
        private readonly DisclosureEligibilityService $eligibilityService,
        private readonly DocumentService $documentService,
        private readonly PublicDisclosurePackageService $packageService,
    ) {
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    public function index(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.view'), 403);

        // Keep the legacy endpoint but route runtime usage to the active disclosure management surface.
        return Redirect::route('executive.transparency-monitoring');
    }

    public function create(): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        $eligibleSources = $this->eligibilityService->listEligibleRecords();
        $procurementDocumentsBySource = $this->packageService->availableProcurementDocumentsForEligibleSources($eligibleSources);

        return Inertia::render('Executive/Disclosures/Create', [
            'eligibleSources' => $eligibleSources,
            'procurementDocumentsBySource' => $procurementDocumentsBySource,
        ]);
    }

    public function edit(PublicDisclosure $public_disclosure): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        abort_unless((string) $public_disclosure->status === 'DRAFT', 403);

        return Inertia::render('Executive/Disclosures/Edit', [
            'disclosure' => $public_disclosure,
            'eligibleSources' => $this->eligibilityService->listEligibleRecords(),
        ]);
    }

    public function store(StorePublicDisclosureRequest $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        $payload = $request->validated();
        $isPublishRequested = strtoupper((string) ($payload['status'] ?? 'DRAFT')) === 'PUBLISHED';

        if ($request->hasFile('main_file')) {
            /** @var UploadedFile $mainFile */
            $mainFile = $request->file('main_file');
            $storedFileName = Str::uuid()->toString().'_'.$mainFile->getClientOriginalName();
            $payload['file_path'] = $this->storeUploadedFileSafely($mainFile, 'public_disclosures', $storedFileName);
        }

        // Always create as DRAFT first so selected procurement documents and attachments can be copied
        $payload['status'] = 'DRAFT';
        $disclosure = $this->disclosureService->createFromPayload($user, $payload);

        // Attach selected procurement documents strictly belonging to this referenced source record
        $procurementDocIds = $request->input('procurement_document_ids', []);
        if (is_array($procurementDocIds) && count($procurementDocIds) > 0) {
            try {
                $this->packageService->attachProcurementDocuments($disclosure, $user, array_map('intval', $procurementDocIds));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed attaching procurement documents: ' . $e->getMessage());
            }
        }

        // Upload any extra municipal supporting files
        $supportingFiles = $request->file('supporting_files', []);
        if (is_array($supportingFiles)) {
            foreach ($supportingFiles as $supportingFile) {
                if (! $supportingFile instanceof UploadedFile) {
                    continue;
                }

                try {
                    $this->documentService->upload($user, 'public_disclosures', (int) $disclosure->disclosure_id, [
                        'title' => pathinfo($supportingFile->getClientOriginalName(), PATHINFO_FILENAME),
                        'file' => $supportingFile,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed uploading supporting municipal file: ' . $e->getMessage());
                }
            }
        }

        // If user requested immediate publishing, publish now (which generates the official certified package PDF)
        if ($isPublishRequested) {
            $disclosure = $this->disclosureService->publish(
                disclosure: $disclosure,
                actor: $user,
                remarks: $payload['remarks'] ?? null,
            );
        } elseif (! $request->hasFile('main_file')) {
            // In draft mode without custom main file, ensure the official package PDF exists so user can preview it
            try {
                $this->packageService->ensureOfficialPackageAttachment($disclosure, $user);
            } catch (\Throwable) {
                // Draft can still be edited if snapshot PDF creation has minor delay
            }
        }

        return Redirect::route('executive.disclosures.show', $disclosure->disclosure_id)
            ->with('success', (string) $disclosure->status === 'PUBLISHED' ? 'Disclosure published successfully with selected attachments.' : 'Disclosure draft created successfully with selected attachments.');
    }

    public function update(UpdatePublicDisclosureRequest $request, PublicDisclosure $public_disclosure): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        $payload = $request->validated();

        $updated = $this->disclosureService->updateDraft($public_disclosure, $user, $payload);

        if (strtoupper((string) ($payload['status'] ?? 'DRAFT')) === 'PUBLISHED') {
            $updated = $this->disclosureService->publish(
                disclosure: $updated,
                actor: $user,
                remarks: $payload['remarks'] ?? null,
            );
        }

        return Redirect::route('executive.disclosures.show', $public_disclosure->disclosure_id)
            ->with('success', (string) $updated->status === 'PUBLISHED' ? 'Disclosure published successfully.' : 'Disclosure draft updated successfully.');
    }

    public function show(PublicDisclosure $public_disclosure): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.view'), 403);

        $history = $this->historyTimelineService->forRecord($user, $public_disclosure);

        $attachments = $this->documentService
            ->listForRelated($user, 'public_disclosures', (int) $public_disclosure->disclosure_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'uploader_name' => $document->uploader?->name,
                'document_kind' => $document->document_kind,
            ])->values();

        $this->packageService->syncSources($public_disclosure);
        $public_disclosure->load(['publisher', 'statusHistory.changedBy', 'sources']);
        $availableProcurementDocuments = (string) $public_disclosure->status === 'DRAFT'
            ? $this->packageService->availableProcurementDocuments($public_disclosure)
                ->map(fn ($document): array => [
                    'document_id' => (int) $document->document_id,
                    'title' => (string) $document->title,
                    'file_name' => (string) $document->file_name,
                    'document_kind' => (string) ($document->document_kind ?? ''),
                    'related_module' => (string) $document->related_module,
                    'related_reference_id' => (int) $document->related_reference_id,
                    'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                    'uploader_name' => $document->uploader?->name,
                ])->values()
            : collect();

        return Inertia::render('Executive/Disclosures/Show', [
            'disclosure' => $public_disclosure,
            'attachments' => $attachments,
            'availableProcurementDocuments' => $availableProcurementDocuments,
            'sources' => $public_disclosure->sources->map(fn ($source): array => [
                'id' => (int) $source->id,
                'source_role' => (string) $source->source_role,
                'source_label' => (string) $source->source_label,
                'source_type' => (string) $source->source_type,
                'source_id' => (int) $source->source_id,
                'snapshot' => $source->snapshot_json ?? [],
            ])->values(),
            'attachmentPermissions' => [
                'upload' => (string) $public_disclosure->status === 'DRAFT',
                'delete' => (string) $public_disclosure->status === 'DRAFT',
            ],
            'statusHistory' => $history['status']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => (string) $entry->new_status,
                'remarks' => $entry->remarks,
                'changed_at' => optional($entry->changed_at)?->toIso8601String(),
                'changed_by' => $entry->changedBy ? [
                    'id' => (int) $entry->changedBy->id,
                    'name' => (string) $entry->changedBy->name,
                    'role' => (string) ($entry->changedBy->role?->name ?? ''),
                    'office' => (string) ($entry->changedBy->office?->name ?? ''),
                ] : null,
            ])->values(),
            'approvalHistory' => $history['approval']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'status' => (string) $entry->status,
                'remarks' => $entry->remarks,
                'approved_at' => optional($entry->approved_at)?->toIso8601String(),
                'approver' => $entry->approver ? [
                    'id' => (int) $entry->approver->id,
                    'name' => (string) $entry->approver->name,
                    'role' => (string) ($entry->approver->role?->name ?? ''),
                    'office' => (string) ($entry->approver->office?->name ?? ''),
                ] : null,
            ])->values(),
        ]);
    }

    public function attachProcurementDocuments(AttachProcurementDocumentsRequest $request, PublicDisclosure $public_disclosure): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        $this->packageService->attachProcurementDocuments(
            disclosure: $public_disclosure,
            actor: $user,
            documentIds: $request->validated('document_ids'),
        );

        return Redirect::route('executive.disclosures.show', $public_disclosure->disclosure_id)
            ->with('success', 'Selected procurement documents were attached for public disclosure review.');
    }

    public function publish(PublishPublicDisclosureRequest $request, PublicDisclosure $public_disclosure): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.publish', $public_disclosure), 403);

        $this->disclosureService->publish(
            disclosure: $public_disclosure,
            actor: $user,
            remarks: $request->validated('remarks'),
        );

        return Redirect::route('executive.disclosures.show', $public_disclosure->disclosure_id)
            ->with('success', 'Disclosure published successfully.');
    }

    /**
     * @return array{title: string, file_name: string, content: string}
     */
    private function buildGeneratedDisclosureAttachment(PublicDisclosure $disclosure): array
    {
        $referenceType = (string) ($disclosure->reference_type ?? '');
        $referenceId = (int) ($disclosure->reference_id ?? 0);
        $sourceLabel = $this->resolveDisclosureSourceLabel($referenceType, $referenceId);
        $preparedAt = now()->format('Y-m-d H:i:s');

        return [
            'title' => sprintf('Source Record Snapshot - %s', (string) $disclosure->title),
            'file_name' => sprintf('disclosure-%d-source-record', (int) $disclosure->disclosure_id),
            'content' => implode(PHP_EOL, [
                'PUBLIC DISCLOSURE SOURCE RECORD SNAPSHOT',
                str_repeat('=', 42),
                sprintf('Disclosure ID: %d', (int) $disclosure->disclosure_id),
                sprintf('Title: %s', (string) $disclosure->title),
                sprintf('Type: %s', (string) $disclosure->type),
                sprintf('Status: %s', (string) $disclosure->status),
                sprintf('Reference Type: %s', $referenceType !== '' ? $referenceType : 'N/A'),
                sprintf('Reference ID: %s', $referenceId > 0 ? (string) $referenceId : 'N/A'),
                sprintf('Resolved Source: %s', $sourceLabel),
                sprintf('Prepared At: %s', $preparedAt),
                '',
                'Description:',
                (string) ($disclosure->description ?? 'No description provided.'),
            ]),
        ];
    }

    private function resolveDisclosureSourceLabel(string $referenceType, int $referenceId): string
    {
        if ($referenceId <= 0 || $referenceType === '') {
            return 'Unknown source record';
        }

        if ($referenceType === PurchaseRequest::class) {
            $record = PurchaseRequest::query()->with('office:id,name')->find($referenceId, ['purchase_request_id', 'office_id', 'status']);

            if ($record) {
                return sprintf('PR-%d | %s | %s', (int) $record->purchase_request_id, $record->office?->name ?? 'Unknown Office', (string) $record->status);
            }
        }

        if ($referenceType === PurchaseOrder::class) {
            $record = PurchaseOrder::query()->with('purchaseRequest.office:id,name')->find($referenceId, ['purchase_order_id', 'purchase_request_id', 'po_number', 'status']);

            if ($record) {
                $code = (string) ($record->po_number ?: ('PO-'.$record->purchase_order_id));

                return sprintf('PO %s | %s | %s', $code, $record->purchaseRequest?->office?->name ?? 'Unknown Office', (string) $record->status);
            }
        }

        if ($referenceType === Ppmp::class) {
            $record = Ppmp::query()->with('office:id,name')->find($referenceId, ['ppmp_id', 'office_id', 'fiscal_year', 'status']);

            if ($record) {
                return sprintf('PPMP-%d | %s | FY %d | %s', (int) $record->ppmp_id, $record->office?->name ?? 'Unknown Office', (int) $record->fiscal_year, (string) $record->status);
            }
        }

        if ($referenceType === DistributionRecord::class) {
            $record = DistributionRecord::query()->with('office:id,name')->find($referenceId, ['distribution_record_id', 'distribution_code', 'office_id', 'status']);

            if ($record) {
                $code = (string) ($record->distribution_code ?: ('DIST-'.$record->distribution_record_id));

                return sprintf('%s | %s | %s', $code, $record->office?->name ?? 'Unknown Office', (string) $record->status);
            }
        }

        return sprintf('%s #%d (not found)', class_basename($referenceType), $referenceId);
    }

    private function storeUploadedFileSafely(UploadedFile $file, string $directory, string $fileName): string
    {
        $primaryDisk = $this->publicDisk();
        try {
            $path = $file->storeAs($directory, $fileName, $primaryDisk);
            if (is_string($path) && trim($path) !== '') {
                return $path;
            }
        } catch (\Throwable) {
            // Fallback when cloud disk fails
        }

        if ($primaryDisk !== 'public') {
            try {
                $path = $file->storeAs($directory, $fileName, 'public');
                if (is_string($path) && trim($path) !== '') {
                    return $path;
                }
            } catch (\Throwable) {
                // Fallback to local
            }
        }

        return $file->storeAs($directory, $fileName, 'local') ?: '';
    }
}
