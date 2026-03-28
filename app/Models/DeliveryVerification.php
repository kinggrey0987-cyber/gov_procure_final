<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryVerification extends Model
{
    use HasFactory;

    protected $primaryKey = 'delivery_verification_id';

    public function getRouteKeyName(): string
    {
        return 'delivery_verification_id';
    }

    protected $fillable = [
        'delivery_id',
        'verified_by',
        'verification_date',
        'verification_status',
        'discrepancy_notes',
        'remarks',
    ];

    protected $casts = [
        'delivery_id' => 'integer',
        'verified_by' => 'integer',
        'verification_date' => 'date',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'delivery_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('verification_status', 'APPROVED');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('verification_status', 'REJECTED');
    }

    public function scopeWithDiscrepancies(Builder $query): Builder
    {
        return $query->whereNotNull('discrepancy_notes');
    }
}
