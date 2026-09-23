<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSpecification extends Model
{
    use HasFactory;

    protected $table = 'item_specifications';

    protected $primaryKey = 'item_specification_id';

    public function getRouteKeyName(): string
    {
        return 'item_specification_id';
    }

    protected $fillable = [
        'item_id',
        'specification',
        'is_active',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
