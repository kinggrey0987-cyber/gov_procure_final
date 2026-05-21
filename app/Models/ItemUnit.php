<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemUnit extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_unit_id';

    protected $fillable = [
        'name',
        'status',
        'created_by',
    ];
}

