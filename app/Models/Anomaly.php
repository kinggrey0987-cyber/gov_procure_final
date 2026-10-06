<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Anomaly extends Model
{
    use HasFactory;

    public const TYPE_PRICE_Z_SCORE = 'PRICE_Z_SCORE';
    public const TYPE_FREQUENT_REQUEST_WINDOW = 'FREQUENT_REQUEST_WINDOW';
    public const TYPE_EXCESSIVE_QUANTITY = 'EXCESSIVE_QUANTITY';
    public const TYPE_PPMP_OVER_REQUEST = 'PPMP_OVER_REQUEST';
    public const TYPE_PPMP_EARLY_DEPLETION = 'PPMP_EARLY_DEPLETION';
    public const TYPE_PPMP_FREQUENCY_IRREGULARITY = 'PPMP_FREQUENCY_IRREGULARITY';
    public const TYPE_PPMP_SCHEDULE_MISMATCH = 'PPMP_SCHEDULE_MISMATCH';
    public const TYPE_DELIVERY_DISCREPANCY = 'DELIVERY_DISCREPANCY';

    public const CATEGORY_FINANCIAL_VARIANCE = 'FINANCIAL_VARIANCE';
    public const CATEGORY_BEHAVIORAL_REQUISITION = 'BEHAVIORAL_REQUISITION';
    public const CATEGORY_SUPPLY_CHAIN_INTEGRITY = 'SUPPLY_CHAIN_INTEGRITY';

    protected $primaryKey = 'anomaly_id';

    public function getRouteKeyName(): string
    {
        return 'anomaly_id';
    }

    protected $fillable = [
        'anomaly_type',
        'reference_type',
        'reference_id',
        'item_id',
        'office_id',
        'detected_value',
        'expected_value',
        'threshold_value',
        'z_score',
        'detection_method',
        'severity',
        'status',
        'detected_at',
        'remarks',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'item_id' => 'integer',
        'office_id' => 'integer',
        'detected_value' => 'decimal:4',
        'expected_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'z_score' => 'decimal:4',
        'detected_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'anomaly_id', 'anomaly_id');
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function approvalHistory(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', strtoupper($severity));
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', strtoupper($status));
    }

    public function scopeByOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeByType(Builder $query, string $anomalyType): Builder
    {
        return $query->where('anomaly_type', strtoupper($anomalyType));
    }

    /**
     * @return array<int, string>
     */
    public static function intendedTypes(): array
    {
        return [
            self::TYPE_PRICE_Z_SCORE,
            self::TYPE_FREQUENT_REQUEST_WINDOW,
            self::TYPE_EXCESSIVE_QUANTITY,
            self::TYPE_PPMP_OVER_REQUEST,
            self::TYPE_PPMP_EARLY_DEPLETION,
            self::TYPE_PPMP_FREQUENCY_IRREGULARITY,
            self::TYPE_PPMP_SCHEDULE_MISMATCH,
            self::TYPE_DELIVERY_DISCREPANCY,
        ];
    }

    public static function validTypes(): array
    {
        return self::intendedTypes();
    }

    public static function availableTypes(): array
    {
        return self::intendedTypes();
    }

    public static function humanizeType(string $type): string
    {
        $aliases = [
            self::TYPE_PRICE_Z_SCORE => 'Price Anomaly',
            'PRICE_ANOMALY' => 'Price Anomaly',
            'Z_SCORE_PRICE_ANOMALY' => 'Price Anomaly',
            self::TYPE_FREQUENT_REQUEST_WINDOW => 'Frequent Request Spike',
            'FREQUENT_REQUEST_SPIKE' => 'Frequent Request Spike',
            self::TYPE_EXCESSIVE_QUANTITY => 'Excessive Quantity Anomaly',
            self::TYPE_PPMP_OVER_REQUEST => 'PPMP Over-Request',
            self::TYPE_PPMP_EARLY_DEPLETION => 'PPMP Early Depletion',
            self::TYPE_PPMP_FREQUENCY_IRREGULARITY => 'PPMP Frequency Irregularity',
            self::TYPE_PPMP_SCHEDULE_MISMATCH => 'PPMP Schedule Mismatch',
            self::TYPE_DELIVERY_DISCREPANCY => 'Delivery Discrepancy',
        ];

        $normalized = strtoupper(trim($type));

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return ucwords(strtolower(str_replace('_', ' ', $type)));
    }

    /**
     * Returns academic and governmental literature foundation and statistical taxonomy metadata.
     *
     * @return array{category: string, category_title: string, mathematical_model: string, decision_boundary: string, literature_citation: string, governance_mandate: string}
     */
    public static function taxonomyForType(string $type): array
    {
        $normalized = strtoupper(trim($type));

        return match ($normalized) {
            self::TYPE_PRICE_Z_SCORE => [
                'category' => self::CATEGORY_FINANCIAL_VARIANCE,
                'category_title' => 'Financial & Procurement Pricing Variance',
                'mathematical_model' => 'Standardized Z-Score Deviation: Z = (X - μ) / σ',
                'decision_boundary' => 'Warning: |Z| ≥ 2.0 (95.45% confidence); Critical: |Z| ≥ 3.0 (99.73% confidence)',
                'literature_citation' => 'Grubbs, F. E. (1969). Procedures for Detecting Outlying Observations in Samples. Technometrics, 11(1), 1-21.',
                'governance_mandate' => 'Philippine RA 9184 IRR Annex H & DBM-PS Municipal Price Index Reference Standards.',
            ],
            self::TYPE_EXCESSIVE_QUANTITY => [
                'category' => self::CATEGORY_BEHAVIORAL_REQUISITION,
                'category_title' => 'Behavioral Requisition Volume Variance',
                'mathematical_model' => 'Shewhart Process Control 2-Sigma Upper Decision Limit: Threshold = μ + 2σ',
                'decision_boundary' => 'Warning: Quantity > μ + 2σ (Top 2.28% of historical consumption distribution)',
                'literature_citation' => 'Montgomery, D. C. (2019). Introduction to Statistical Quality Control (8th ed.). John Wiley & Sons.',
                'governance_mandate' => 'COA Circular 2023-004 Supply Consumption Integrity & Inventory Buffers.',
            ],
            self::TYPE_FREQUENT_REQUEST_WINDOW, self::TYPE_PPMP_FREQUENCY_IRREGULARITY => [
                'category' => self::CATEGORY_BEHAVIORAL_REQUISITION,
                'category_title' => 'Behavioral Requisition Velocity & Demand Splitting',
                'mathematical_model' => 'Sliding-Window Frequency Burst Ratio: Velocity = Request_Count_30D / μ_Departmental',
                'decision_boundary' => 'Warning: Frequency > 1.8 × μ (Threshold for potential artificial contract splitting)',
                'literature_citation' => 'World Bank (2020). Procurement Data Analytics Guide: Red-Flag Indicators for Splitting Requisitions.',
                'governance_mandate' => 'Philippine RA 9184 Rule II Sec. 7 (Prohibition of Artificial Splitting of Contracts and Requisitions).',
            ],
            self::TYPE_PPMP_EARLY_DEPLETION => [
                'category' => self::CATEGORY_BEHAVIORAL_REQUISITION,
                'category_title' => 'PPMP Budget Execution & Consumption Trajectory',
                'mathematical_model' => 'Fiscal Midpoint Burn-Rate Depletion Ratio: Cumulative_Spend_Ratio / Elapsed_Fiscal_Time_Ratio',
                'decision_boundary' => 'Critical: ≥85% consumed before 50% elapsed fiscal calendar (Velocity Ratio > 1.70)',
                'literature_citation' => 'DBM-PS Circular 2020-01 (Annual PPMP Budget Execution & Mid-Year Re-alignment Protocols).',
                'governance_mandate' => 'Local Government Code of 1991 (RA 7160) Sec. 305 (Fundamental Principles of Local Fiscal Administration).',
            ],
            self::TYPE_DELIVERY_DISCREPANCY => [
                'category' => self::CATEGORY_SUPPLY_CHAIN_INTEGRITY,
                'category_title' => 'Supply Chain Inspection & Fulfillment Quality Discrepancy',
                'mathematical_model' => 'Inspection Defect Discrepancy Rate: Rate = (Missing_Qty + Damaged_Qty) / Expected_Qty × 100%',
                'decision_boundary' => 'Warning: Defect Rate > 5.0%; Critical: Defect Rate ≥ 20.0%',
                'literature_citation' => 'Montgomery, D. C. (2019). Introduction to Statistical Quality Control (8th ed.). John Wiley & Sons.',
                'governance_mandate' => 'Philippine RA 9184 Sec. 68 (Liquidated Damages) & COA Inspection & Acceptance Report (IAR) Rules.',
            ],
            default => [
                'category' => self::CATEGORY_BEHAVIORAL_REQUISITION,
                'category_title' => 'Procurement Operational Baseline Deviation',
                'mathematical_model' => 'Statistical Mean Variance Ratio: Deviation = (Actual - Expected) / Expected',
                'decision_boundary' => 'Operational tolerance threshold exceeded',
                'literature_citation' => 'Montgomery, D. C. (2019). Statistical Quality Control.',
                'governance_mandate' => 'RA 9184 Governance and Transparency Standards.',
            ],
        };
    }

    public function getTaxonomyDetails(): array
    {
        return self::taxonomyForType((string) $this->anomaly_type);
    }
}
