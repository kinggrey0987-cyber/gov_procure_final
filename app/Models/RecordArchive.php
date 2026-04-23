<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordArchive extends Model
{
    use HasFactory;

    protected $table = 'record_archives';

    protected $primaryKey = 'archive_id';

    public $timestamps = false;

    protected $fillable = [
        'archive_year',
        'archive_scope',
        'file_name',
        'file_path',
        'file_size',
        'generated_by',
        'generated_at',
        'notes',
    ];

    protected $casts = [
        'archive_year' => 'integer',
        'file_size' => 'integer',
        'generated_by' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'archive_id';
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}

