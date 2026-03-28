<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Alert extends Model
{
    use HasFactory;

    protected $table = 'alerts';

    protected $primaryKey = 'alerts_id';

    public function getRouteKeyName(): string
    {
        return 'alerts_id';
    }

    protected $fillable = [
        'alert_type',
        'severity',
        'anomaly_id',
        'supply_request_id',
        'purchase_order_id',
        'supplier_id',
        'office_id',
        'alert_message',
        'detected_at',
        'reviewed_by',
        'status',
    ];

    protected $casts = [
        'anomaly_id' => 'integer',
        'supply_request_id' => 'integer',
        'purchase_order_id' => 'integer',
        'supplier_id' => 'integer',
        'office_id' => 'integer',
        'reviewed_by' => 'integer',
        'detected_at' => 'datetime',
    ];

    public function anomaly(): BelongsTo
    {
        return $this->belongsTo(Anomaly::class, 'anomaly_id', 'anomaly_id');
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id', 'supply_request_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function scopeByType(Builder $query, string $alertType): Builder
    {
        return $query->where('alert_type', strtoupper($alertType));
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeExecutiveRelevant(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNotNull('anomaly_id')
                ->orWhereIn('alert_type', ['ANOMALY_DETECTED', 'EXECUTIVE_REVIEW']);
        });
    }
}
