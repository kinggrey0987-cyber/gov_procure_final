<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ppmp extends Model
{
    use HasFactory;

    protected $table = 'ppmp';

    protected $primaryKey = 'ppmp_id';

    public function getRouteKeyName(): string
    {
        return 'ppmp_id';
    }

    protected $fillable = [
        'office_id',
        'prepared_by',
        'approved_by',
        'fiscal_year',
        'status',
        'total_estimated_budget',
        'date_prepared',
        'date_approved',
        'remarks',
    ];

    protected $casts = [
        'office_id' => 'integer',
        'prepared_by' => 'integer',
        'approved_by' => 'integer',
        'fiscal_year' => 'integer',
        'total_estimated_budget' => 'decimal:2',
        'date_prepared' => 'date',
        'date_approved' => 'date',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class, 'ppmp_id', 'ppmp_id');
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
