<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
