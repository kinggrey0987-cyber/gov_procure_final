<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DistributionRecord extends Model
{
    use HasFactory;

    protected $primaryKey = 'distribution_record_id';

    public function getRouteKeyName(): string
    {
        return 'distribution_record_id';
    }

    protected $fillable = [
        'distribution_code',
        'supply_request_id',
        'delivery_id',
        'office_id',
        'prepared_by',
        'received_by',
        'release_date',
        'storage_location',
        'status',
        'notes',
    ];

    protected $casts = [
        'supply_request_id' => 'integer',
        'delivery_id' => 'integer',
        'office_id' => 'integer',
        'prepared_by' => 'integer',
        'received_by' => 'integer',
        'release_date' => 'date',
    ];

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id', 'supply_request_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'delivery_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributionItem::class, 'distribution_record_id', 'distribution_record_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'distribution_record_id', 'distribution_record_id');
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

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', 'RELEASED');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'RECEIVED');
    }
}
