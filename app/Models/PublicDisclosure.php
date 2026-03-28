<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PublicDisclosure extends Model
{
    use HasFactory;

    protected $table = 'public_disclosures';

    protected $primaryKey = 'disclosure_id';

    public function getRouteKeyName(): string
    {
        return 'disclosure_id';
    }

    protected $fillable = [
        'title',
        'type',
        'reference_type',
        'reference_id',
        'description',
        'published_by',
        'published_at',
        'file_path',
        'status',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'published_by' => 'integer',
        'published_at' => 'datetime',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'related_reference_id', 'disclosure_id')
            ->where('related_module', 'public_disclosures');
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function approvalHistory(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', strtoupper($status));
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', strtoupper($type));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'PUBLISHED')->whereNotNull('published_at');
    }
}
