<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Claim extends Model
{
    use HasFactory;

    protected $table = 'claims';

    protected $primaryKey = 'claims_id';

    public function getRouteKeyName(): string
    {
        return 'claims_id';
    }

    protected $fillable = [
        'claim_code',
        'distribution_record_id',
        'distribution_item_id',
        'parent_claim_id',
        'source_type',
        'claimed_by',
        'office_id',
        'claim_type',
        'claim_date',
        'claim_location',
        'quantity_disputed',
        'quantity_damaged',
        'quantity_missing',
        'remarks',
        'status',
        'resolution_type',
        'replacement_quantity',
    ];

    protected $casts = [
        'distribution_record_id' => 'integer',
        'distribution_item_id' => 'integer',
        'parent_claim_id' => 'integer',
        'claimed_by' => 'integer',
        'office_id' => 'integer',
        'claim_date' => 'date',
        'quantity_disputed' => 'decimal:2',
        'quantity_damaged' => 'decimal:2',
        'quantity_missing' => 'decimal:2',
        'replacement_quantity' => 'decimal:2',
    ];

    public function supplyRequest(): HasOneThrough
    {
        return $this->hasOneThrough(
            SupplyRequest::class,
            DistributionRecord::class,
            'distribution_record_id',
            'supply_request_id',
            'distribution_record_id',
            'supply_request_id'
        );
    }

    public function distributionRecord(): BelongsTo
    {
        return $this->belongsTo(DistributionRecord::class, 'distribution_record_id', 'distribution_record_id');
    }

    public function distributionItem(): BelongsTo
    {
        return $this->belongsTo(DistributionItem::class, 'distribution_item_id', 'distribution_item_id');
    }

    public function parentClaim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'parent_claim_id', 'claims_id');
    }

    public function followUpClaims(): HasMany
    {
        return $this->hasMany(Claim::class, 'parent_claim_id', 'claims_id');
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'related_reference_id', 'claims_id')
            ->where('related_module', 'claims');
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
        return $query->where('status', 'OPEN');
    }

    public function scopeInReview(Builder $query): Builder
    {
        return $query->where('status', 'IN_REVIEW');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'RESOLVED');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'REJECTED');
    }
}
