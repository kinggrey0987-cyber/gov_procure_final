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

        $taxonomy = $anomaly->getTaxonomyDetails();
        $prices = $series->pluck('value')->map(fn ($v) => (float) $v);
        $sampleSize = $prices->count();
        $stdDev = null;
        if ($sampleSize >= 2 && $expected !== null) {
            $variance = $prices->map(fn ($v) => ($v - $expected) ** 2)->avg();
            $stdDev = round(sqrt((float) $variance), 4);
        }

        $baselineParams = [
            'mean' => $expected,
            'std_dev' => $stdDev,
            'sample_size' => $sampleSize,
            'z_score' => $zScore,
            'warning_threshold' => $expected !== null && $stdDev !== null ? round($expected + (2.0 * $stdDev), 4) : $threshold,
            'critical_threshold' => $expected !== null && $stdDev !== null ? round($expected + (3.0 * $stdDev), 4) : null,
            'confidence_level' => $zScore !== null ? (abs($zScore) >= 3.0 ? '99.73% (Critical 3σ Outlier)' : '95.45% (Warning 2σ Outlier)') : 'Normal Distribution',
        ];

        return [
            'type' => 'PRICE_Z_SCORE',
            'title' => 'Price Z-Score Evidence (Grubbs / Shewhart Model)',
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
            'taxonomy' => $taxonomy,
            'baseline_parameters' => $baselineParams,
            'literature_citation' => $taxonomy['literature_citation'],
            'governance_mandate' => $taxonomy['governance_mandate'],
            'mathematical_model' => $taxonomy['mathematical_model'],
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
        $taxonomy = $anomaly->getTaxonomyDetails();

        $baselineParams = [
            'mean' => $expected,
            'std_dev' => null,
            'sample_size' => null,
            'z_score' => $this->toFloat($anomaly->z_score),
            'warning_threshold' => $this->toFloat($anomaly->threshold_value),
            'critical_threshold' => null,
            'confidence_level' => $anomaly->z_score ? (abs((float) $anomaly->z_score) >= 3.0 ? '99.73% (Critical)' : '95.45% (Warning)') : 'Deterministic Baseline',
        ];

        return [
            'type' => strtoupper((string) $anomaly->anomaly_type),
            'title' => Anomaly::humanizeType((string) $anomaly->anomaly_type),
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
            'taxonomy' => $taxonomy,
            'baseline_parameters' => $baselineParams,
            'literature_citation' => $taxonomy['literature_citation'],
            'governance_mandate' => $taxonomy['governance_mandate'],
            'mathematical_model' => $taxonomy['mathematical_model'],
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

