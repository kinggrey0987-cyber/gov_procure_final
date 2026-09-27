<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $primaryKey = 'movement_id';

    public $timestamps = false;

    public function getRouteKeyName(): string
    {
        return 'movement_id';
    }

    protected $fillable = [
        'inventory_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity',
        'performed_by',
        'last_updated',
        'movement_date',
        'remarks',
    ];

    protected $casts = [
        'inventory_id' => 'integer',
        'reference_id' => 'integer',
        'quantity' => 'decimal:2',
        'performed_by' => 'integer',
        'last_updated' => 'datetime',
        'movement_date' => 'date:Y-m-d',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'inventory_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeByType(Builder $query, string $movementType): Builder
    {
        return $query->where('movement_type', strtoupper($movementType));
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('movement_date')->orderByDesc('movement_id');
    }
}
