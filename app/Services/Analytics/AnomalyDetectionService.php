<?php

namespace App\Services\Analytics;

use App\Models\Anomaly;
use App\Models\PpmpItem;
use App\Models\PurchaseOrderItem;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AnomalyDetectionService
{
    public function __construct(private readonly AnomalyService $anomalyService)
    {
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function runExecutiveDetections(User $actor): Collection
    {
        if (!$actor->isExecutive()) {
            throw ValidationException::withMessages([
                'role' => 'Only EXEC_OFFICER can run anomaly detection.',
            ]);
        }

        $detected = collect()
            ->merge($this->detectPriceZScoreAnomalies($actor))
            ->merge($this->detectFrequentRequestAnomalies($actor))
            ->merge($this->detectExcessiveQuantityAnomalies($actor))
            ->merge($this->detectPpmpOverRequestAnomalies($actor))
            ->merge($this->detectPpmpEarlyDepletionAnomalies($actor))
            ->merge($this->detectPpmpFrequencyIrregularities($actor))
            ->merge($this->detectDeliveryDiscrepancyAnomalies($actor));

        $this->anomalyService->collapseActiveDuplicates();

        return $detected;
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectPriceZScoreAnomalies(User $actor): Collection
    {
        $created = collect();

        $items = PurchaseOrderItem::query()
            ->with(['purchaseOrder.purchaseRequest:purchase_request_id,office_id', 'purchaseOrder.supplyRequest:supply_request_id,office_id'])
            ->select(['purchase_order_item_id', 'purchase_order_id', 'item_id', 'unit_price'])
            ->whereNotNull('item_id')
            ->whereHas('purchaseOrder')
            ->get()
            ->groupBy('item_id');

        foreach ($items as $itemId => $rows) {
            $prices = $rows->pluck('unit_price')->map(fn ($value) => (float) $value);
            if ($prices->count() < 3) {
                continue;
            }

            $n = $prices->count();
            $mean = (float) $prices->avg();
            $variance = (float) $prices->map(fn ($price) => ($price - $mean) ** 2)->avg();
            $std = sqrt($variance);
            if ($std <= 0) {
                continue;
            }

            $latest = $rows->sortByDesc('purchase_order_item_id')->first();
            if (!$latest) {
                continue;
            }

            $latestPrice = (float) $latest->unit_price;
            $zScore = ($latestPrice - $mean) / $std;

            if (abs($zScore) < 2.0) {
                continue;
            }

            $purchaseOrder = $latest->purchaseOrder;
            $officeId = (int) ($purchaseOrder?->purchaseRequest?->office_id ?? $purchaseOrder?->supplyRequest?->office_id ?? 0) ?: null;

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'PRICE_Z_SCORE',
                'reference_type' => PurchaseOrderItem::class,
                'reference_id' => (int) $latest->purchase_order_item_id,
                'item_id' => (int) $itemId,
                'office_id' => $officeId,
                'detected_value' => $latestPrice,
                'expected_value' => round($mean, 4),
                'threshold_value' => round($mean + (2.0 * $std), 4),
                'z_score' => round($zScore, 4),
                'detection_method' => 'Z_SCORE',
                'severity' => abs($zScore) >= 3 ? 'HIGH' : 'MEDIUM',
                'remarks' => sprintf(
                    'Statistical Z-Score outlier (Z=%.2fσ, N=%d). Baseline μ=₱%.2f, σ=₱%.2f versus Grubbs (1969) / Montgomery (2019) 2σ/3σ boundaries.',
                    $zScore,
                    $n,
                    $mean,
                    $std
                ),
            ]));
        }

        return $created;
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectFrequentRequestAnomalies(User $actor): Collection
    {
        $created = collect();

        $windowStart = now()->subDays(30)->toDateString();

        $grouped = SupplyRequest::query()
            ->whereDate('request_date', '>=', $windowStart)
            ->selectRaw('office_id, count(*) as request_count')
            ->groupBy('office_id')
            ->get();

        $counts = $grouped->pluck('request_count')->map(fn ($value) => (float) $value);
        if ($counts->count() < 2) {
            return $created;
        }

        $mean = $counts->avg();

        foreach ($grouped as $row) {
            $detected = (float) $row->request_count;
            if ($detected <= ($mean * 1.8)) {
                continue;
            }

            $latestRequest = SupplyRequest::query()
                ->where('office_id', (int) $row->office_id)
                ->latest('supply_request_id')
                ->first();

            if (!$latestRequest) {
                continue;
            }

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'FREQUENT_REQUEST_WINDOW',
                'reference_type' => SupplyRequest::class,
                'reference_id' => (int) $latestRequest->supply_request_id,
                'item_id' => null,
                'office_id' => (int) $row->office_id,
                'detected_value' => $detected,
                'expected_value' => $mean,
                'threshold_value' => round($mean * 1.8, 4),
                'z_score' => null,
                'detection_method' => 'SLIDING_WINDOW_30D',
                'severity' => 'MEDIUM',
                'remarks' => 'Frequent office request volume detected within a sliding window.',
            ]));
        }

        return $created;
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectExcessiveQuantityAnomalies(User $actor): Collection
    {
        $created = collect();

        $items = SupplyRequestItem::query()->select(['item_id', 'quantity_requested'])->whereNotNull('item_id')->get()->groupBy('item_id');

        foreach ($items as $itemId => $rows) {
            $quantities = $rows->pluck('quantity_requested')->map(fn ($value) => (float) $value);
            if ($quantities->count() < 3) {
                continue;
            }

            $n = $quantities->count();
            $mean = (float) $quantities->avg();
            $variance = (float) $quantities->map(fn ($value) => ($value - $mean) ** 2)->avg();
            $std = sqrt($variance);
            $threshold = $mean + (2 * $std);

            $latest = $rows->sortByDesc('supply_request_item_id')->first();
            if (!$latest) {
                continue;
            }

            $latestQty = (float) $latest->quantity_requested;
            if ($latestQty <= $threshold) {
                continue;
            }

            $zScore = $std > 0 ? ($latestQty - $mean) / $std : 2.0;

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'EXCESSIVE_QUANTITY',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $latest->supply_request_item_id,
                'item_id' => (int) $itemId,
                'office_id' => (int) ($latest->supplyRequest->office_id ?? 0) ?: null,
                'detected_value' => $latestQty,
                'expected_value' => round($mean, 4),
                'threshold_value' => round($threshold, 4),
                'z_score' => round($zScore, 4),
                'detection_method' => 'MEAN_PLUS_2_SIGMA',
                'severity' => $zScore >= 3.0 ? 'HIGH' : 'MEDIUM',
                'remarks' => sprintf(
                    'Requisition quantity exceeds Shewhart 2-sigma control limit (Z=%.2fσ, N=%d). Baseline μ=%.2f, σ=%.2f.',
                    $zScore,
                    $n,
                    $mean,
                    $std
                ),
            ]));
        }

        return $created;
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectPpmpOverRequestAnomalies(User $actor): Collection
    {
        $created = collect();

        $ppmpItems = PpmpItem::query()
            ->with('ppmp:ppmp_id,office_id')
            ->get(['ppmp_item_id', 'ppmp_id', 'item_id', 'quantity']);

        foreach ($ppmpItems as $ppmpItem) {
            $plannedQuantity = (float) $ppmpItem->quantity;
            if ($plannedQuantity <= 0) {
                continue;
            }

            $itemRows = SupplyRequestItem::query()
                ->where('ppmp_item_id', (int) $ppmpItem->ppmp_item_id)
                ->whereHas('supplyRequest', function ($query) use ($ppmpItem): void {
                    $query->where('ppmp_id', (int) $ppmpItem->ppmp_id);
                })
                ->with('supplyRequest:supply_request_id,ppmp_id,office_id,request_date')
                ->get(['supply_request_item_id', 'ppmp_item_id', 'item_id', 'supply_request_id', 'quantity_requested']);

            if ($itemRows->isEmpty()) {
                continue;
            }

            $totalRequested = (float) $itemRows->sum(fn (SupplyRequestItem $row): float => (float) $row->quantity_requested);
            if ($totalRequested <= $plannedQuantity) {
                continue;
            }

            /** @var SupplyRequestItem|null $latest */
            $latest = $itemRows->sortByDesc('supply_request_item_id')->first();
            if (!$latest) {
                continue;
            }

            $overRequestAmount = $totalRequested - $plannedQuantity;

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'PPMP_OVER_REQUEST',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $latest->supply_request_item_id,
                'item_id' => (int) ($ppmpItem->item_id ?: $latest->item_id),
                'office_id' => (int) ($latest->supplyRequest->office_id ?? $ppmpItem->ppmp?->office_id ?? 0) ?: null,
                'detected_value' => $totalRequested,
                'expected_value' => $plannedQuantity,
                'threshold_value' => $plannedQuantity,
                'detection_method' => 'PPMP_PLAN_CONSUMPTION',
                'severity' => $totalRequested >= ($plannedQuantity * 1.25) ? 'HIGH' : 'MEDIUM',
                'remarks' => sprintf(
                    'PPMP item requests exceeded plan by %.2f units (requested %.2f vs planned %.2f).',
                    $overRequestAmount,
                    $totalRequested,
                    $plannedQuantity,
                ),
            ]));
        }

        return $created;
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectPpmpEarlyDepletionAnomalies(User $actor): Collection
    {
        $created = collect();

        $ppmpItems = PpmpItem::query()
            ->with('ppmp:ppmp_id,fiscal_year,office_id')
            ->get(['ppmp_item_id', 'ppmp_id', 'item_id', 'quantity']);

        foreach ($ppmpItems as $ppmpItem) {
            $plannedQuantity = (float) $ppmpItem->quantity;
            if ($plannedQuantity <= 0) {
                continue;
            }

            $itemRows = SupplyRequestItem::query()
                ->where('ppmp_item_id', (int) $ppmpItem->ppmp_item_id)
                ->whereHas('supplyRequest', function ($query) use ($ppmpItem): void {
                    $query->where('ppmp_id', (int) $ppmpItem->ppmp_id);
                })
                ->with('supplyRequest:supply_request_id,office_id,request_date')
                ->get(['supply_request_item_id', 'item_id', 'supply_request_id', 'quantity_requested']);

            if ($itemRows->isEmpty()) {
                continue;
            }

            $totalRequested = (float) $itemRows->sum(fn (SupplyRequestItem $row): float => (float) $row->quantity_requested);
            if ($totalRequested < ($plannedQuantity * 0.85)) {
                continue;
            }

            $latestRequestDate = $itemRows
                ->map(fn (SupplyRequestItem $row): ?Carbon => $row->supplyRequest?->request_date instanceof Carbon ? $row->supplyRequest->request_date : null)
                ->filter()
                ->sortByDesc(fn (Carbon $date): int => $date->timestamp)
                ->first();

            if (!$latestRequestDate instanceof Carbon) {
                continue;
            }

            $fiscalYear = (int) ($ppmpItem->ppmp?->fiscal_year ?? (int) $latestRequestDate->year);
            $periodStart = Carbon::create($fiscalYear, 1, 1)->startOfDay();
            $periodEnd = Carbon::create($fiscalYear, 12, 31)->endOfDay();

            if ($latestRequestDate->lt($periodStart)) {
                continue;
            }

            $elapsedDays = max($periodStart->diffInDays($latestRequestDate) + 1, 1);
            $totalDays = max($periodStart->diffInDays($periodEnd) + 1, 1);
            $elapsedRatio = min($elapsedDays / $totalDays, 1.0);

            if ($elapsedRatio > 0.50) {
                continue;
            }

            /** @var SupplyRequestItem|null $latest */
            $latest = $itemRows->sortByDesc('supply_request_item_id')->first();
            if (!$latest) {
                continue;
            }

            $expectedConsumptionAtMidpoint = $plannedQuantity * 0.50;

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'PPMP_EARLY_DEPLETION',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $latest->supply_request_item_id,
                'item_id' => (int) ($ppmpItem->item_id ?: $latest->item_id),
                'office_id' => (int) ($latest->supplyRequest->office_id ?? $ppmpItem->ppmp?->office_id ?? 0) ?: null,
                'detected_value' => $totalRequested,
                'expected_value' => $expectedConsumptionAtMidpoint,
                'threshold_value' => $plannedQuantity * 0.85,
                'detection_method' => 'PPMP_EARLY_DEPLETION_WINDOW',
                'severity' => $totalRequested >= $plannedQuantity ? 'HIGH' : 'MEDIUM',
                'remarks' => sprintf(
                    'PPMP item consumed %.2f/%.2f before midpoint of fiscal usage window.',
                    $totalRequested,
                    $plannedQuantity,
                ),
            ]));
        }

        return $created;
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectPpmpFrequencyIrregularities(User $actor): Collection
    {
        $created = collect();

        $windowStart = now()->subDays(30)->toDateString();

        $grouped = SupplyRequestItem::query()
            ->whereNotNull('ppmp_item_id')
            ->whereHas('supplyRequest', function ($query) use ($windowStart): void {
                $query->whereDate('request_date', '>=', $windowStart)
                    ->whereNotNull('ppmp_id');
            })
            ->selectRaw('ppmp_item_id, count(*) as request_count')
            ->groupBy('ppmp_item_id')
            ->get();

        $counts = $grouped->pluck('request_count')->map(fn ($value): float => (float) $value);
        if ($counts->count() < 2) {
            return $created;
        }

        $mean = $counts->avg();
        $threshold = $mean * 1.8;

        foreach ($grouped as $row) {
            $detected = (float) $row->request_count;
            if ($detected < 3 || $detected <= $threshold) {
                continue;
            }

            $latestRequestItem = SupplyRequestItem::query()
                ->where('ppmp_item_id', (int) $row->ppmp_item_id)
                ->whereHas('supplyRequest', function ($query) use ($windowStart): void {
                    $query->whereDate('request_date', '>=', $windowStart)
                        ->whereNotNull('ppmp_id');
                })
                ->with(['supplyRequest:supply_request_id,office_id', 'ppmpItem:ppmp_item_id,item_id'])
                ->latest('supply_request_item_id')
                ->first(['supply_request_item_id', 'ppmp_item_id', 'item_id', 'supply_request_id']);

            if (!$latestRequestItem instanceof SupplyRequestItem) {
                continue;
            }

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'PPMP_FREQUENCY_IRREGULARITY',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $latestRequestItem->supply_request_item_id,
                'item_id' => (int) ($latestRequestItem->ppmpItem?->item_id ?: $latestRequestItem->item_id),
                'office_id' => (int) ($latestRequestItem->supplyRequest->office_id ?? 0) ?: null,
                'detected_value' => $detected,
                'expected_value' => $mean,
                'threshold_value' => $threshold,
                'detection_method' => 'PPMP_SLIDING_WINDOW_30D',
                'severity' => $detected >= ($threshold * 1.4) ? 'HIGH' : 'MEDIUM',
                'remarks' => 'Frequent requests detected against the same PPMP item within 30 days.',
            ]));
        }

        return $created;
    }

    /**
     * Detects deliveries with elevated discrepancy (shortage / damage) rates under SUPPLY_CHAIN_INTEGRITY taxonomy.
     *
     * @return Collection<int, Anomaly>
     */
    public function detectDeliveryDiscrepancyAnomalies(User $actor): Collection
    {
        $created = collect();

        $discrepantDeliveries = \App\Models\Delivery::query()
            ->whereHas('items', function ($query): void {
                $query->where('damaged_quantity', '>', 0)
                    ->orWhere('missing_quantity', '>', 0);
            })
            ->with(['items.purchaseOrderItem', 'purchaseOrder.purchaseRequest', 'supplier'])
            ->latest('delivery_id')
            ->take(25)
            ->get();

        foreach ($discrepantDeliveries as $delivery) {
            $totalMissing = (float) $delivery->items->sum('missing_quantity');
            $totalDamaged = (float) $delivery->items->sum('damaged_quantity');
            $totalDiscrepancy = $totalMissing + $totalDamaged;
            $totalExpected = (float) $delivery->items->sum('expected_quantity');

            if ($totalDiscrepancy <= 0 || $totalExpected <= 0) {
                continue;
            }

            $discrepancyRate = ($totalDiscrepancy / $totalExpected) * 100.0;
            $firstItemId = (int) ($delivery->items->first()?->purchaseOrderItem?->item_id ?? 0) ?: null;

            $created->push($this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => Anomaly::TYPE_DELIVERY_DISCREPANCY,
                'reference_type' => \App\Models\Delivery::class,
                'reference_id' => (int) $delivery->delivery_id,
                'item_id' => $firstItemId,
                'office_id' => (int) ($delivery->purchaseOrder?->purchaseRequest?->office_id ?? 0) ?: null,
                'detected_value' => round($discrepancyRate, 2),
                'expected_value' => 0.0,
                'threshold_value' => 5.0,
                'z_score' => null,
                'detection_method' => 'INSPECTION_DEFECT_RATE',
                'severity' => $discrepancyRate >= 20.0 ? 'HIGH' : 'MEDIUM',
                'remarks' => sprintf(
                    'Delivery intake %s exhibited %.1f%% shortage/damage discrepancy (%.2f missing, %.2f damaged of %.2f expected).',
                    $delivery->delivery_code,
                    $discrepancyRate,
                    $totalMissing,
                    $totalDamaged,
                    $totalExpected
                ),
            ]));
        }

        return $created;
    }
}
