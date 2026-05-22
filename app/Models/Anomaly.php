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
        ];
    }
}
