<?php

namespace App\Services\Executive;

use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Support\Collection;

class DisclosureEligibilityService
{
    /**
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function listEligibleRecords(): array
    {
        $approvedPpmps = Ppmp::query()
            ->with('office:id,name')
            ->where('status', 'APPROVED')
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get(['ppmp_id', 'office_id', 'fiscal_year', 'total_estimated_budget', 'updated_at'])
            ->map(static fn (Ppmp $ppmp): array => [
                'label' => sprintf(
                    'PPMP-%d-%04d | %s | FY %d | %s',
                    (int) $ppmp->fiscal_year,
                    (int) $ppmp->ppmp_id,
                    $ppmp->office?->name ?? 'Unknown Office',
                    (int) $ppmp->fiscal_year,
                    number_format((float) $ppmp->total_estimated_budget, 2)
                ),
                'source_group' => 'Approved PPMP',
                'source_code' => sprintf('PPMP-%d-%04d', (int) $ppmp->fiscal_year, (int) $ppmp->ppmp_id),
                'office' => $ppmp->office?->name ?? 'Unknown Office',
                'release_date' => optional($ppmp->updated_at)?->format('Y-m-d'),
                'reference_type' => Ppmp::class,
                'reference_id' => (int) $ppmp->ppmp_id,
                'suggested_type' => 'PPMP_SUMMARY',
                'suggested_title' => sprintf('Approved PPMP Summary - %s (FY %d)', $ppmp->office?->name ?? 'Office', (int) $ppmp->fiscal_year),
            ])
            ->values();

        $completedPurchaseOrders = PurchaseOrder::query()
            ->with(['purchaseRequest.office:id,name'])
            ->whereIn('status', ['COMPLETED', 'PLACED'])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get(['purchase_order_id', 'purchase_request_id', 'po_number', 'status', 'total_amount', 'updated_at'])
            ->map(static fn (PurchaseOrder $order): array => [
                'label' => sprintf(
                    'PO %s | %s | %s',
                    (string) ($order->po_number ?? ('#'.$order->purchase_order_id)),
                    $order->purchaseRequest?->office?->name ?? 'Unknown Office',
                    number_format((float) $order->total_amount, 2)
                ),
                'source_group' => 'Completed Purchase Order',
                'source_code' => (string) ($order->po_number ?? ('PO-'.$order->purchase_order_id)),
                'office' => $order->purchaseRequest?->office?->name ?? 'Unknown Office',
                'release_date' => optional($order->updated_at)?->format('Y-m-d'),
                'reference_type' => PurchaseOrder::class,
                'reference_id' => (int) $order->purchase_order_id,
                'suggested_type' => 'PROCUREMENT_SUMMARY',
                'suggested_title' => sprintf('Procurement Summary - PO %s', (string) ($order->po_number ?? ('#'.$order->purchase_order_id))),
            ])
            ->values();

        $recentPurchaseRequests = PurchaseRequest::query()
            ->with('office:id,name')
            ->whereIn('status', ['OPEN', 'PROCESSING'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['purchase_request_id', 'pr_number', 'office_id', 'status', 'updated_at'])
            ->map(static function (PurchaseRequest $purchaseRequest): array {
                $code = (string) ($purchaseRequest->pr_number ?: ('PR-'.str_pad((string) $purchaseRequest->purchase_request_id, 4, '0', STR_PAD_LEFT)));
                return [
                    'label' => sprintf(
                        '%s | %s | %s',
                        $code,
                        $purchaseRequest->office?->name ?? 'Unknown Office',
                        (string) $purchaseRequest->status
                    ),
                    'source_group' => 'Purchase Request',
                    'source_code' => $code,
                    'office' => $purchaseRequest->office?->name ?? 'Unknown Office',
                    'release_date' => optional($purchaseRequest->updated_at)?->format('Y-m-d'),
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $purchaseRequest->purchase_request_id,
                    'suggested_type' => 'PROCUREMENT_SUMMARY',
                    'suggested_title' => sprintf('Procurement Summary - %s', $code),
                ];
            })
            ->values();

        $distributionSummaries = DistributionRecord::query()
            ->with('office:id,name')
            ->where('status', 'RECEIVED')
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get(['distribution_record_id', 'distribution_code', 'office_id', 'status', 'release_date', 'updated_at'])
            ->map(static fn (DistributionRecord $record): array => [
                'label' => sprintf(
                    '%s | %s | Released %s',
                    (string) ($record->distribution_code ?? ('DIST-'.$record->distribution_record_id)),
                    $record->office?->name ?? 'Unknown Office',
                    optional($record->release_date)?->format('Y-m-d') ?? 'N/A'
                ),
                'source_group' => 'Distribution Summary',
                'source_code' => (string) ($record->distribution_code ?? ('DIST-'.$record->distribution_record_id)),
                'office' => $record->office?->name ?? 'Unknown Office',
                'release_date' => optional($record->release_date)?->format('Y-m-d'),
                'reference_type' => DistributionRecord::class,
                'reference_id' => (int) $record->distribution_record_id,
                'suggested_type' => 'DISTRIBUTION_SUMMARY',
                'suggested_title' => sprintf('Distribution Summary - %s', (string) ($record->distribution_code ?? ('DIST-'.$record->distribution_record_id))),
            ])
            ->values();

        return [
            'approved_ppmps' => $approvedPpmps,
            'completed_purchase_orders' => $completedPurchaseOrders,
            'recent_purchase_requests' => $recentPurchaseRequests,
            'distribution_summaries' => $distributionSummaries,
        ];
    }
}
