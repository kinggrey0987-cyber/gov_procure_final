<?php

namespace App\Services\Executive;

use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
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
                    'PPMP-%d | %s | FY %d | %s',
                    (int) $ppmp->ppmp_id,
                    $ppmp->office?->name ?? 'Unknown Office',
                    (int) $ppmp->fiscal_year,
                    number_format((float) $ppmp->total_estimated_budget, 2)
                ),
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
                'reference_type' => PurchaseOrder::class,
                'reference_id' => (int) $order->purchase_order_id,
                'suggested_type' => 'PROCUREMENT_SUMMARY',
                'suggested_title' => sprintf('Procurement Summary - PO %s', (string) ($order->po_number ?? ('#'.$order->purchase_order_id))),
            ])
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
                'reference_type' => DistributionRecord::class,
                'reference_id' => (int) $record->distribution_record_id,
                'suggested_type' => 'DISTRIBUTION_SUMMARY',
                'suggested_title' => sprintf('Distribution Summary - %s', (string) ($record->distribution_code ?? ('DIST-'.$record->distribution_record_id))),
            ])
            ->values();

        return [
            'approved_ppmps' => $approvedPpmps,
            'completed_purchase_orders' => $completedPurchaseOrders,
            'distribution_summaries' => $distributionSummaries,
        ];
    }
}
