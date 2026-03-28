<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_category_id';

    protected $fillable = [
        'category_name',
        'standard_delivery_days',
        'description',
        'status',
    ];
}
