<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StatusHistory extends Model
{
    use HasFactory;

    protected $table = 'status_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'old_status',
        'new_status',
        'changed_by',
        'remarks',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
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
