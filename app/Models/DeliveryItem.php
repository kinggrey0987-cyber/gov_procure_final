<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'delivery_item_id';

    public function getRouteKeyName(): string
    {
        return 'delivery_item_id';
    }

    protected $fillable = [
        'delivery_id',
        'purchase_order_item_id',
        'expected_quantity',
        'received_quantity',
        'damaged_quantity',
        'missing_quantity',
        'item_condition',
        'remarks',
    ];

    protected $casts = [
        'delivery_id' => 'integer',
        'purchase_order_item_id' => 'integer',
        'expected_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'damaged_quantity' => 'decimal:2',
        'missing_quantity' => 'decimal:2',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'delivery_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id', 'purchase_order_item_id');
    }
}
