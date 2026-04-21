<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Document;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use Illuminate\Support\Facades\DB;
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
}

