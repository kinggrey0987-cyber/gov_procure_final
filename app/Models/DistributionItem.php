<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'distribution_item_id';

    public function getRouteKeyName(): string
    {
        return 'distribution_item_id';
    }

    protected $fillable = [
        'distribution_record_id',
        'item_id',
        'received_by',
        'item_name_snapshot',
        'quantity_released',
        'unit',
        'remarks',
    ];

    protected $casts = [
        'distribution_record_id' => 'integer',
        'item_id' => 'integer',
        'received_by' => 'integer',
        'quantity_released' => 'decimal:2',
    ];

    public function distributionRecord(): BelongsTo
    {
        return $this->belongsTo(DistributionRecord::class, 'distribution_record_id', 'distribution_record_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeForItem(Builder $query, int $itemId): Builder
    {
        return $query->where('item_id', $itemId);
    }
}
