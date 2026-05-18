<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicDisclosureSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'disclosure_id',
        'source_type',
        'source_id',
        'source_role',
        'source_label',
        'sort_order',
        'snapshot_json',
    ];

    protected $casts = [
        'disclosure_id' => 'integer',
        'source_id' => 'integer',
        'sort_order' => 'integer',
        'snapshot_json' => 'array',
    ];

    public function disclosure(): BelongsTo
    {
        return $this->belongsTo(PublicDisclosure::class, 'disclosure_id', 'disclosure_id');
    }
}
