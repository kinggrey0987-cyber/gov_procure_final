<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $primaryKey = 'purchase_request_id';

    public function getRouteKeyName(): string
    {
        return 'purchase_request_id';
    }

    protected $fillable = [
        'supply_request_id',
        'office_id',
        'requested_by',
        'status',
        'remarks',
    ];

    protected $casts = [
        'supply_request_id' => 'integer',
        'office_id' => 'integer',
        'requested_by' => 'integer',
    ];

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id', 'supply_request_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id', 'purchase_request_id');
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['OPEN', 'RETURNED_FOR_REVISION']);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'PROCESSING');
    }
}
