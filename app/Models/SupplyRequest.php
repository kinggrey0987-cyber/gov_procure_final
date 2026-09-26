<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupplyRequest extends Model
{
    use HasFactory;

    protected $primaryKey = 'supply_request_id';

    public function getRouteKeyName(): string
    {
        return 'supply_request_id';
    }

    protected $fillable = [
        'request_code',
        'office_id',
        'requested_by',
        'ppmp_id',
        'request_date',
        'needed_by_date',
        'request_category',
        'item_category_id',
        'purpose',
        'priority_level',
        'status',
        'review_notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'office_id' => 'integer',
        'requested_by' => 'integer',
        'ppmp_id' => 'integer',
        'item_category_id' => 'integer',
        'approved_by' => 'integer',
        'request_date' => 'date',
        'needed_by_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id', 'item_category_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id', 'ppmp_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class, 'supply_request_id', 'supply_request_id');
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'supply_request_id', 'supply_request_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supply_request_id', 'supply_request_id');
    }

    public function distributionRecords(): HasMany
    {
        return $this->hasMany(DistributionRecord::class, 'supply_request_id', 'supply_request_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'supply_request_id', 'supply_request_id');
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function approvalHistory(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'SUBMITTED');
    }
}
