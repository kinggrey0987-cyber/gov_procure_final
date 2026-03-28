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
        'supplier_id',
        'actual_delivery_date',
        'expected_arrival_date',
        'received_by',
        'receipt_status',
        'remarks',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'supplier_id' => 'integer',
        'received_by' => 'integer',
        'actual_delivery_date' => 'date',
        'expected_arrival_date' => 'date',
    ];

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
