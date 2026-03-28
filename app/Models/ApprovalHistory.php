<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalHistory extends Model
{
    use HasFactory;

    protected $table = 'approval_history';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'approved_by',
        'status',
        'remarks',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function scopeForReference(Builder $query, string $referenceType, int $referenceId): Builder
    {
        return $query
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }
}
