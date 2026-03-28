<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'purchase_order_item_id';

    public function getRouteKeyName(): string
    {
        return 'purchase_order_item_id';
    }

    protected $fillable = [
        'purchase_order_id',
        'supply_request_item_id',
        'item_id',
        'item_name_snapshot',
        'specification',
        'quantity_ordered',
        'unit',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'supply_request_item_id' => 'integer',
        'item_id' => 'integer',
        'quantity_ordered' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function supplyRequestItem(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestItem::class, 'supply_request_item_id', 'supply_request_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'purchase_order_item_id', 'purchase_order_item_id');
    }
}
