<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $primaryKey = 'inventory_id';

    public function getRouteKeyName(): string
    {
        return 'inventory_id';
    }

    protected $fillable = [
        'item_id',
        'available_stock',
        'stock_on_hand',
        'reserved_quantity',
        'released_quantity',
        'low_stock_threshold',
        'last_updated',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'available_stock' => 'decimal:2',
        'stock_on_hand' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
        'released_quantity' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
        'last_updated' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_id', 'inventory_id');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('available_stock', '<=', 'low_stock_threshold');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('available_stock', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('available_stock', '<=', 0);
    }
}
