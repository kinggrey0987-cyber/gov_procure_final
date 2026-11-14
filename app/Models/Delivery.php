<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Delivery extends Model
{
    use HasFactory;

    protected $primaryKey = 'delivery_id';

    public function getRouteKeyName(): string
    {
        return 'delivery_id';
    }

    protected $fillable = [
        'delivery_code',
        'purchase_order_id',
        'batch_number',
        'total_batches',
        'delivery_schedule_type',
        'is_final_batch',
        'supplier_id',
        'actual_delivery_date',
        'expected_arrival_date',
        'delivery_receipt_no',
        'sales_invoice_no',
        'sales_invoice_date',
        'sales_invoice_amount',
        'received_by',
        'receipt_status',
        'remarks',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'batch_number' => 'integer',
        'total_batches' => 'integer',
        'is_final_batch' => 'boolean',
        'supplier_id' => 'integer',
        'received_by' => 'integer',
        'actual_delivery_date' => 'date',
        'expected_arrival_date' => 'date',
        'sales_invoice_date' => 'date',
        'sales_invoice_amount' => 'decimal:2',
    ];

    protected $appends = [
        'is_zero_cost_replacement',
        'batch_label',
    ];

    public function getBatchLabelAttribute(): string
    {
        $batch = (int) ($this->batch_number ?: 1);
        $scheduleType = (string) ($this->delivery_schedule_type ?? 'SINGLE_DELIVERY');
        $explicitTotal = $this->total_batches ? (int) $this->total_batches : null;
        $po = $this->relationLoaded('purchaseOrder') ? $this->purchaseOrder : null;
        $poDeliveriesCount = $po && isset($po->deliveries_count) ? (int) $po->deliveries_count : null;
        $poStatus = $po ? (string) ($po->status ?? '') : '';

        $isSingleDelivery = $scheduleType !== 'STAGGERED_BATCH'
            && $batch === 1
            && ($explicitTotal === null || $explicitTotal <= 1)
            && ($poDeliveriesCount === null || $poDeliveriesCount <= 1)
            && $poStatus !== 'PARTIALLY_DELIVERED';

        if ($isSingleDelivery) {
            return 'Single Delivery';
        }

        if ($poDeliveriesCount !== null && $poDeliveriesCount > 1 && in_array($poStatus, ['COMPLETED', 'CLOSED'], true)) {
            $displayTotal = $poDeliveriesCount;
        } elseif ($explicitTotal !== null && $explicitTotal > 1) {
            // Preserve declared total batches while expanding gracefully if follow-ups exceed the initial estimate.
            $displayTotal = max($explicitTotal, $poDeliveriesCount ?? 0, $batch);
        } else {
            $displayTotal = null;
        }

        if ($displayTotal !== null) {
            $label = sprintf('Batch #%d of %d', $batch, $displayTotal);
        } else {
            $label = sprintf('Batch #%d', $batch);
        }

        $maxBatch = $po && isset($po->max_batch_number) ? (int) $po->max_batch_number : null;
        $isPoCompleted = in_array($poStatus, ['COMPLETED', 'CLOSED'], true);
        $isActuallyFinal = ($isPoCompleted && $maxBatch !== null)
            ? ($batch === $maxBatch)
            : (bool) $this->is_final_batch;

        if ($isActuallyFinal) {
            $label .= ' (Final)';
        }

        return $label;
    }

    public function getIsZeroCostReplacementAttribute(): bool
    {
        return (bool) ($this->purchaseOrder?->is_zero_cost_replacement ?? false);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'related_reference_id', 'delivery_id')
            ->where('related_module', 'deliveries');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'delivery_id', 'delivery_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(DeliveryVerification::class, 'delivery_id', 'delivery_id');
    }

    public function distributionRecords(): HasMany
    {
        return $this->hasMany(DistributionRecord::class, 'delivery_id', 'delivery_id');
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function approvalHistory(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('receipt_status', 'PENDING');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('receipt_status', 'RECEIVED');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('receipt_status', 'VERIFIED');
    }

    public function scopePartiallyReceived(Builder $query): Builder
    {
        return $query->where('receipt_status', 'PARTIALLY_RECEIVED');
    }
}
