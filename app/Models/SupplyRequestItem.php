<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyRequestItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'supply_request_item_id';

    public function getRouteKeyName(): string
    {
        return 'supply_request_item_id';
    }

    protected $fillable = [
        'supply_request_id',
        'item_id',
        'ppmp_item_id',
        'item_name_snapshot',
        'quantity_requested',
    ];

    protected $casts = [
        'supply_request_id' => 'integer',
        'item_id' => 'integer',
        'ppmp_item_id' => 'integer',
        'quantity_requested' => 'decimal:2',
    ];

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id', 'supply_request_id');
    }

    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class, 'ppmp_item_id', 'ppmp_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'supply_request_item_id', 'supply_request_item_id');
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->whereHas('supplyRequest', function (Builder $sq) use ($officeId): void {
            $sq->where('office_id', $officeId);
        });
    }
}
