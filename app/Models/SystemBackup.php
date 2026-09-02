<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemBackup extends Model
{
    use HasFactory;

    protected $table = 'system_backups';

    protected $primaryKey = 'backup_id';

    public $timestamps = false;

    protected $fillable = [
        'backup_type',
        'file_name',
        'file_path',
        'file_size',
        'sha256_checksum',
        'table_metrics',
        'payload',
        'created_by',
        'created_at',
        'backup_status',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'table_metrics' => 'array',
        'payload' => 'array',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'backup_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

