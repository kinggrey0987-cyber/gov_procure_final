<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Document;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RecordArchive;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\SystemBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DemoSeedAuditService
{
    /**
     * @return array{issues: array<int, string>, fixes: array<int, string>}
     */
    public function auditAndFix(): array
    {
        $issues = [];
        $fixes = [];

        DB::transaction(function () use (&$issues, &$fixes): void {
            $this->fixSupplyRequestsWithNonApprovedPpmp($issues, $fixes);
            $this->fixOrphanSupplyRequestItems($issues, $fixes);
            $this->fixPurchaseRequestsWithoutSupplyRequest($issues, $fixes);
            $this->fixPurchaseOrdersWithoutPurchaseRequest($issues, $fixes);
            $this->fixDistributionContext($issues, $fixes);
            $this->fixPublicDisclosureFiles($issues, $fixes);
            $this->fixArchiveFiles($issues, $fixes);
            $this->fixBackupFiles($issues, $fixes);
            $this->auditPublishedDisclosureReferences($issues, $fixes);
        });

        return [
            'issues' => $issues,
            'fixes' => $fixes,
        ];
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixSupplyRequestsWithNonApprovedPpmp(array &$issues, array &$fixes): void
    {
        $requests = SupplyRequest::query()
            ->with('ppmp')
            ->whereHas('ppmp', fn ($query) => $query->where('status', '!=', 'APPROVED'))
            ->get();

        foreach ($requests as $request) {
            $issues[] = sprintf(
                'Supply request %s references non-approved PPMP %d (%s).',
                (string) $request->request_code,
                (int) $request->ppmp_id,
                (string) ($request->ppmp?->status ?? 'UNKNOWN')
            );

            if (PurchaseRequest::query()->where('supply_request_id', (int) $request->supply_request_id)->exists()) {
                $fixes[] = sprintf(
                    'Skipped PPMP swap for supply request %s because procurement already exists.',
                    (string) $request->request_code
                );

                continue;
            }

            $approved = Ppmp::query()
                ->where('office_id', (int) $request->office_id)
                ->where('status', 'APPROVED')
                ->orderByDesc('fiscal_year')
                ->orderByDesc('ppmp_id')
                ->first();

            if (! $approved instanceof Ppmp) {
                $fixes[] = sprintf(
                    'No approved PPMP available for office %d to fix request %s.',
                    (int) $request->office_id,
                    (string) $request->request_code
                );

                continue;
            }

            $request->forceFill(['ppmp_id' => (int) $approved->ppmp_id])->save();

            $fixes[] = sprintf(
                'Repointed supply request %s to approved PPMP %d.',
                (string) $request->request_code,
                (int) $approved->ppmp_id
            );
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixOrphanSupplyRequestItems(array &$issues, array &$fixes): void
    {
        $orphans = SupplyRequestItem::query()
            ->whereDoesntHave('supplyRequest')
            ->orWhereDoesntHave('ppmpItem')
            ->get();

        foreach ($orphans as $orphan) {
            $issues[] = sprintf('Orphan supply_request_item detected: %d.', (int) $orphan->supply_request_item_id);
            $orphan->delete();
            $fixes[] = sprintf('Deleted orphan supply_request_item %d.', (int) $orphan->supply_request_item_id);
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixPurchaseRequestsWithoutSupplyRequest(array &$issues, array &$fixes): void
    {
        $invalid = PurchaseRequest::query()->whereDoesntHave('supplyRequest')->get();

        foreach ($invalid as $request) {
            $issues[] = sprintf('Purchase request %d has missing supply request.', (int) $request->purchase_request_id);
            $request->delete();
            $fixes[] = sprintf('Deleted invalid purchase request %d.', (int) $request->purchase_request_id);
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixPurchaseOrdersWithoutPurchaseRequest(array &$issues, array &$fixes): void
    {
        $invalid = PurchaseOrder::query()->whereDoesntHave('purchaseRequest')->get();

        foreach ($invalid as $order) {
            $issues[] = sprintf('Purchase order %s has missing purchase request.', (string) $order->po_number);
            $order->delete();
            $fixes[] = sprintf('Deleted invalid purchase order %s.', (string) $order->po_number);
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixDistributionContext(array &$issues, array &$fixes): void
    {
        $records = DistributionRecord::query()->with(['supplyRequest', 'delivery.purchaseOrder'])->get();

        foreach ($records as $record) {
            if (! $record->supplyRequest) {
                $issues[] = sprintf('Distribution record %s has missing supply request.', (string) $record->distribution_code);
                $record->delete();
                $fixes[] = sprintf('Deleted distribution record %s without supply request.', (string) $record->distribution_code);
                continue;
            }

            if ($record->delivery_id === null) {
                continue;
            }

            $delivery = $record->delivery;
            if (! $delivery instanceof Delivery) {
                $issues[] = sprintf('Distribution record %s references missing delivery %d.', (string) $record->distribution_code, (int) $record->delivery_id);
                $record->forceFill(['delivery_id' => null])->save();
                $fixes[] = sprintf('Removed broken delivery linkage for distribution record %s.', (string) $record->distribution_code);
                continue;
            }

            $deliverySupplyRequestId = (int) ($delivery->purchaseOrder?->supply_request_id ?? 0);
            if ($deliverySupplyRequestId !== (int) $record->supply_request_id) {
                $issues[] = sprintf('Distribution record %s has mismatched delivery context.', (string) $record->distribution_code);
                $record->forceFill(['delivery_id' => null])->save();
                $fixes[] = sprintf('Detached mismatched delivery from distribution record %s.', (string) $record->distribution_code);
            }
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixPublicDisclosureFiles(array &$issues, array &$fixes): void
    {
        $published = PublicDisclosure::query()->published()->get();

        foreach ($published as $disclosure) {
            $path = ltrim((string) $disclosure->file_path, '/');
            if ($path === '' || ! Storage::disk('public')->exists($path)) {
                $issues[] = sprintf('Published disclosure %d has missing main file.', (int) $disclosure->disclosure_id);

                $fallbackPath = sprintf('reports/public_disclosures/disclosure-%d.txt', (int) $disclosure->disclosure_id);
                Storage::disk('public')->put(
                    $fallbackPath,
                    sprintf("GovSupply Connect disclosure fallback\nDisclosure ID: %d\n", (int) $disclosure->disclosure_id)
                );

                $disclosure->forceFill(['file_path' => $fallbackPath])->save();
                $fixes[] = sprintf('Rebuilt missing public disclosure file for disclosure %d.', (int) $disclosure->disclosure_id);
            }

            $documents = Document::query()
                ->where('related_module', 'public_disclosures')
                ->where('related_reference_id', (int) $disclosure->disclosure_id)
                ->get();

            foreach ($documents as $document) {
                $docPath = ltrim((string) $document->file_path, '/');
                if ($docPath !== '' && Storage::disk('public')->exists($docPath)) {
                    continue;
                }

                $issues[] = sprintf('Disclosure attachment %d has missing file.', (int) $document->document_id);
                $rebuiltPath = sprintf('documents/public_disclosures/%d/rebuilt-%d.txt', (int) $disclosure->disclosure_id, (int) $document->document_id);

                Storage::disk('public')->put(
                    $rebuiltPath,
                    sprintf("Rebuilt attachment\nDisclosure ID: %d\nDocument ID: %d\n", (int) $disclosure->disclosure_id, (int) $document->document_id)
                );

                $document->forceFill([
                    'file_path' => $rebuiltPath,
                    'file_name' => basename($rebuiltPath),
                ])->save();

                $fixes[] = sprintf('Rebuilt missing attachment file for document %d.', (int) $document->document_id);
            }
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixArchiveFiles(array &$issues, array &$fixes): void
    {
        if (! Schema::hasTable('record_archives')) {
            return;
        }

        $archives = RecordArchive::query()->get();

        foreach ($archives as $archive) {
            $path = ltrim((string) $archive->file_path, '/');
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                continue;
            }

            $issues[] = sprintf('Archive %d has missing file path.', (int) $archive->archive_id);

            $fallbackPath = sprintf(
                'record-archives/%d/rebuilt/archive-%d.json',
                (int) $archive->archive_year,
                (int) $archive->archive_id,
            );

            $payload = [
                'archive_id' => (int) $archive->archive_id,
                'archive_year' => (int) $archive->archive_year,
                'archive_scope' => (string) $archive->archive_scope,
                'generated_by' => (int) $archive->generated_by,
                'generated_at' => optional($archive->generated_at)?->toIso8601String(),
                'note' => 'Rebuilt by DemoSeedAuditService because original archive file was missing.',
            ];

            Storage::disk('local')->put($fallbackPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $archive->forceFill([
                'file_path' => $fallbackPath,
                'file_name' => basename($fallbackPath),
                'file_size' => Storage::disk('local')->size($fallbackPath),
            ])->save();

            $fixes[] = sprintf('Rebuilt missing archive file for archive %d.', (int) $archive->archive_id);
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function fixBackupFiles(array &$issues, array &$fixes): void
    {
        if (! Schema::hasTable('system_backups')) {
            return;
        }

        $backups = SystemBackup::query()->get();

        foreach ($backups as $backup) {
            $status = (string) $backup->backup_status;
            $path = ltrim((string) $backup->file_path, '/');

            if ($status !== 'completed') {
                continue;
            }

            $hasLocalFile = $path !== '' && Storage::disk('local')->exists($path);
            $hasPayload = is_array($backup->payload) && array_key_exists('content', $backup->payload);

            if ($hasLocalFile || $hasPayload) {
                continue;
            }

            $issues[] = sprintf('Completed backup %d has no downloadable content.', (int) $backup->backup_id);

            $fallbackPath = sprintf(
                'system-backups/%s/rebuilt-backup-%d.json',
                (string) $backup->backup_type,
                (int) $backup->backup_id,
            );

            $content = [
                'backup_id' => (int) $backup->backup_id,
                'backup_type' => (string) $backup->backup_type,
                'backup_status' => $status,
                'created_by' => (int) $backup->created_by,
                'created_at' => optional($backup->created_at)?->toIso8601String(),
                'note' => 'Rebuilt by DemoSeedAuditService because original backup artifact was missing.',
            ];

            Storage::disk('local')->put($fallbackPath, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $backup->forceFill([
                'file_path' => $fallbackPath,
                'file_name' => basename($fallbackPath),
                'file_size' => Storage::disk('local')->size($fallbackPath),
                'payload' => [
                    'format' => 'json',
                    'content' => $content,
                ],
            ])->save();

            $fixes[] = sprintf('Rebuilt downloadable payload for completed backup %d.', (int) $backup->backup_id);
        }
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $fixes
     */
    private function auditPublishedDisclosureReferences(array &$issues, array &$fixes): void
    {
        $published = PublicDisclosure::query()->published()->get();

        foreach ($published as $disclosure) {
            $referenceType = (string) ($disclosure->reference_type ?? '');
            $referenceId = (int) ($disclosure->reference_id ?? 0);

            if ($referenceType === '' || $referenceId <= 0) {
                continue;
            }

            if ($referenceType === Ppmp::class) {
                $ppmp = Ppmp::query()->where('ppmp_id', $referenceId)->first();
                if (! $ppmp) {
                    $issues[] = sprintf('Published disclosure %d references missing PPMP %d.', (int) $disclosure->disclosure_id, $referenceId);
                    continue;
                }

                if ((string) $ppmp->status !== 'APPROVED') {
                    $issues[] = sprintf(
                        'Published disclosure %d references non-approved PPMP %d (%s).',
                        (int) $disclosure->disclosure_id,
                        $referenceId,
                        (string) $ppmp->status
                    );

                    $disclosure->forceFill([
                        'status' => 'DRAFT',
                        'published_at' => null,
                        'published_by' => null,
                    ])->save();

                    $fixes[] = sprintf('Downgraded disclosure %d to DRAFT due to non-approved PPMP reference.', (int) $disclosure->disclosure_id);
                }
            }
        }
    }
}

