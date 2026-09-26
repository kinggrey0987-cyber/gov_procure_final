<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_category_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'category_name',
        'standard_delivery_days',
        'description',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_category_id', 'item_category_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_item_categories', 'item_category_id', 'supplier_id')
            ->withTimestamps();
    }

    public function supplyRequests(): HasMany
    {
        return $this->hasMany(SupplyRequest::class, 'item_category_id', 'item_category_id');
    }
}
