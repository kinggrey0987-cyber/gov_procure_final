<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PpmpItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'ppmp_item_id';

    public function getRouteKeyName(): string
    {
        return 'ppmp_item_id';
    }

    protected $fillable = [
        'ppmp_id',
        'item_id',
        'item_category_id',
        'quantity',
        'unit_cost',
        'estimated_budget',
        'procurement_schedule',
        'remarks',
    ];

    protected $casts = [
        'ppmp_id' => 'integer',
        'item_id' => 'integer',
        'item_category_id' => 'integer',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'estimated_budget' => 'decimal:2',
    ];

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id', 'ppmp_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id', 'item_category_id');
    }

    public function supplyRequestItems(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class, 'ppmp_item_id', 'ppmp_item_id');
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->whereHas('ppmp', function (Builder $ppmpQuery) use ($officeId): void {
            $ppmpQuery->where('office_id', $officeId);
        });
    }
}
