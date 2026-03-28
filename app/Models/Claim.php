<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'claimed_by',
        'office_id',
        'claim_type',
        'claim_date',
        'claim_location',
        'remarks',
        'status',
    ];

    protected $casts = [
        'distribution_record_id' => 'integer',
        'claimed_by' => 'integer',
        'office_id' => 'integer',
        'claim_date' => 'date',
    ];

    public function distributionRecord(): BelongsTo
    {
        return $this->belongsTo(DistributionRecord::class, 'distribution_record_id', 'distribution_record_id');
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
