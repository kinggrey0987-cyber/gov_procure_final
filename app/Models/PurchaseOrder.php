<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const FINISHED_STATUSES = [
        'PLACED',
        'PARTIALLY_DELIVERED',
        'DELIVERED',
        'COMPLETED',
        'CLOSED',
    ];

    protected $primaryKey = 'purchase_order_id';

    public function isFinished(): bool
    {
        return in_array((string) $this->status, self::FINISHED_STATUSES, true);
    }

    public function getRouteKeyName(): string
    {
        return 'purchase_order_id';
    }

    protected $fillable = [
        'purchase_request_id',
        'supply_request_id',
        'supplier_id',
        'created_by',
        'approved_by',
        'po_number',
        'procurement_method',
        'status',
        'expected_delivery_date',
        'date_approved',
        'total_amount',
        'terms_notes',
        'remarks',
    ];

    protected $casts = [
        'purchase_request_id' => 'integer',
        'supply_request_id' => 'integer',
        'supplier_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'expected_delivery_date' => 'date',
        'date_approved' => 'date',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = [
        'is_zero_cost_replacement',
        'parent_po',
        'priority_level',
    ];

    public function getPriorityLevelAttribute(): string
    {
        if ($this->relationLoaded('purchaseRequest') && $this->purchaseRequest) {
            return $this->purchaseRequest->priority_level;
        }
        if ($this->purchase_request_id) {
            return PurchaseRequest::query()->find($this->purchase_request_id)?->priority_level ?? 'NORMAL';
        }
        return 'NORMAL';
    }

    public function getParentPoAttribute(): ?self
    {
        return $this->purchaseRequest?->sourceDelivery?->purchaseOrder;
    }

    public function getIsZeroCostReplacementAttribute(): bool
    {
        return in_array((string) ($this->purchaseRequest?->source_type ?? ''), ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
            || (int) ($this->purchaseRequest?->source_delivery_id ?? 0) > 0
            || (int) ($this->purchaseRequest?->source_claim_id ?? 0) > 0;
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id', 'supply_request_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'related_reference_id', 'purchase_order_id')
            ->where('related_module', 'purchase_orders');
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function approvalHistory(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopePlaced(Builder $query): Builder
    {
        return $query->where('status', 'PLACED');
    }
}
