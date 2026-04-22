<?php

namespace App\Services\Analytics;

use App\Models\Anomaly;
use App\Models\PurchaseOrderItem;

class AnomalyEvidenceService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Anomaly $anomaly): array
    {
        $type = strtoupper((string) $anomaly->anomaly_type);

        return match ($type) {
            Anomaly::TYPE_PRICE_Z_SCORE => $this->buildPriceZScoreEvidence($anomaly),
            default => $this->buildGenericEvidence($anomaly),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPriceZScoreEvidence(Anomaly $anomaly): array
    {
        $itemId = $anomaly->item_id ? (int) $anomaly->item_id : null;

        $series = collect();
        if ($itemId) {
            $series = PurchaseOrderItem::query()
                ->where('item_id', $itemId)
                ->whereNotNull('unit_price')
                ->orderBy('purchase_order_item_id')
                ->get(['purchase_order_item_id', 'unit_price'])
                ->map(function (PurchaseOrderItem $row) use ($anomaly): array {
                    return [
                        'label' => sprintf('PO Item #%d', (int) $row->purchase_order_item_id),
                        'value' => round((float) $row->unit_price, 4),
                        'is_detected' => (int) $row->purchase_order_item_id === (int) $anomaly->reference_id,
                    ];
                })
                ->take(24)
                ->values();
        }

        $detected = $this->toFloat($anomaly->detected_value);
        $expected = $this->toFloat($anomaly->expected_value);
        $threshold = $this->toFloat($anomaly->threshold_value);
        $zScore = $this->toFloat($anomaly->z_score);

        $percentDelta = null;
        if ($detected !== null && $expected !== null && abs($expected) > 0.000001) {
            $percentDelta = round((($detected - $expected) / $expected) * 100, 2);
        }

        return [
            'type' => 'PRICE_Z_SCORE',
            'title' => 'Price Z-Score Evidence',
            'narrative' => $percentDelta === null
                ? 'Detected price outlier from procurement-side purchase order pricing.'
                : sprintf(
                    'Detected price is %s%.2f%% versus baseline mean for the same item.',
                    $percentDelta >= 0 ? '+' : '',
                    $percentDelta,
                ),
            'metrics' => [
                'detected_value' => $detected,
                'expected_value' => $expected,
                'threshold_value' => $threshold,
                'z_score' => $zScore,
                'percent_delta' => $percentDelta,
            ],
            'series' => $series->all(),
            'comparison' => [
                [
                    'label' => 'Price Check',
                    'first' => $detected ?? 0,
                    'second' => $expected ?? 0,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGenericEvidence(Anomaly $anomaly): array
    {
        $detected = $this->toFloat($anomaly->detected_value);
        $expected = $this->toFloat($anomaly->expected_value);

        return [
            'type' => strtoupper((string) $anomaly->anomaly_type),
            'title' => 'Anomaly Evidence',
            'narrative' => (string) ($anomaly->remarks ?: 'System detected an anomaly pattern that requires executive review.'),
            'metrics' => [
                'detected_value' => $detected,
                'expected_value' => $expected,
                'threshold_value' => $this->toFloat($anomaly->threshold_value),
                'z_score' => $this->toFloat($anomaly->z_score),
                'percent_delta' => ($detected !== null && $expected !== null && abs($expected) > 0.000001)
                    ? round((($detected - $expected) / $expected) * 100, 2)
                    : null,
            ],
            'series' => [],
            'comparison' => [
                [
                    'label' => 'Detected vs Expected',
                    'first' => $detected ?? 0,
                    'second' => $expected ?? 0,
                ],
            ],
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }
}

