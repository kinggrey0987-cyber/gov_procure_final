<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'item_category_id',
        'item_name',
        'description',
        'default_unit',
        'pieces_per_box',
        'status',
    ];

    protected $casts = [
        'pieces_per_box' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id', 'item_category_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'item_id', 'item_id');
    }

    public function distributionItems(): HasMany
    {
        return $this->hasMany(DistributionItem::class, 'item_id', 'item_id');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class, 'item_id', 'item_id');
    }
}
