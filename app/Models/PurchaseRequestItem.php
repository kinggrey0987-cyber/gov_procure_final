<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'purchase_request_item_id';

    public function getRouteKeyName(): string
    {
        return 'purchase_request_item_id';
    }

     protected $fillable = [
         'purchase_request_id',
         'item_id',
         'source_delivery_item_id',
         'quantity_requested',
         'remarks',
     ];

     protected $casts = [
         'purchase_request_id' => 'integer',
         'item_id' => 'integer',
         'source_delivery_item_id' => 'integer',
         'quantity_requested' => 'decimal:2',
     ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'purchase_request_id');
    }

     public function item(): BelongsTo
     {
         return $this->belongsTo(Item::class, 'item_id', 'item_id');
     }

     public function sourceDeliveryItem(): BelongsTo
     {
         return $this->belongsTo(DeliveryItem::class, 'source_delivery_item_id', 'delivery_item_id');
     }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->whereHas('purchaseRequest', function (Builder $purchaseRequestQuery) use ($officeId): void {
            $purchaseRequestQuery->where('office_id', $officeId);
        });
    }
}
