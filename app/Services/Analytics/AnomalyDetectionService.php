<?php

namespace App\Services\Analytics;

use App\Models\Anomaly;
use App\Models\PurchaseOrderItem;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
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

        return collect()
            ->merge($this->detectPriceZScoreAnomalies($actor))
            ->merge($this->detectFrequentRequestAnomalies($actor))
            ->merge($this->detectExcessiveQuantityAnomalies($actor));
    }

    /**
     * @return Collection<int, Anomaly>
     */
    public function detectPriceZScoreAnomalies(User $actor): Collection
    {
        $created = collect();

        $items = PurchaseOrderItem::query()->select(['item_id', 'unit_price'])->whereNotNull('item_id')->get()->groupBy('item_id');

        foreach ($items as $itemId => $rows) {
            $prices = $rows->pluck('unit_price')->map(fn ($value) => (float) $value);
            if ($prices->count() < 3) {
                continue;
            }

            $mean = $prices->avg();
            $variance = $prices->map(fn ($price) => ($price - $mean) ** 2)->avg();
            $std = sqrt((float) $variance);
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

            $created->push($this->anomalyService->create($actor, [
                'anomaly_type' => 'PRICE_Z_SCORE',
                'reference_type' => PurchaseOrderItem::class,
                'reference_id' => (int) $latest->purchase_order_item_id,
                'item_id' => (int) $itemId,
                'office_id' => (int) ($purchaseOrder->purchaseRequest->office_id ?? 0) ?: null,
                'detected_value' => $latestPrice,
                'expected_value' => $mean,
                'threshold_value' => 2.0,
                'z_score' => round($zScore, 4),
                'detection_method' => 'Z_SCORE',
                'severity' => abs($zScore) >= 3 ? 'HIGH' : 'MEDIUM',
                'remarks' => 'Detected price outlier using z-score.',
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

            $created->push($this->anomalyService->create($actor, [
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

            $mean = $quantities->avg();
            $variance = $quantities->map(fn ($value) => ($value - $mean) ** 2)->avg();
            $std = sqrt((float) $variance);
            $threshold = $mean + (2 * $std);

            $latest = $rows->sortByDesc('supply_request_item_id')->first();
            if (!$latest) {
                continue;
            }

            $latestQty = (float) $latest->quantity_requested;
            if ($latestQty <= $threshold) {
                continue;
            }

            $created->push($this->anomalyService->create($actor, [
                'anomaly_type' => 'EXCESSIVE_QUANTITY',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $latest->supply_request_item_id,
                'item_id' => (int) $itemId,
                'office_id' => (int) ($latest->supplyRequest->office_id ?? 0) ?: null,
                'detected_value' => $latestQty,
                'expected_value' => $mean,
                'threshold_value' => $threshold,
                'z_score' => null,
                'detection_method' => 'MEAN_PLUS_2_SIGMA',
                'severity' => 'HIGH',
                'remarks' => 'Requested quantity exceeded mean + 2 sigma threshold.',
            ]));
        }

        return $created;
    }
}
