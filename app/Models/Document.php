<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $primaryKey = 'document_id';

    public function getRouteKeyName(): string
    {
        return 'document_id';
    }

    protected $fillable = [
        'uploaded_by',
        'title',
        'file_name',
        'file_path',
        'related_module',
        'related_reference_id',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_by' => 'integer',
        'related_reference_id' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('related_module', strtolower($module));
    }

    public function scopeForReference(Builder $query, string $module, int $referenceId): Builder
    {
        return $query->forModule($module)->where('related_reference_id', $referenceId);
    }

    public function scopeUploadedBy(Builder $query, int $userId): Builder
    {
        return $query->where('uploaded_by', $userId);
    }

    public function isRelatedTo(string $module, int $referenceId): bool
    {
        return (string) $this->related_module === strtolower($module)
            && (int) $this->related_reference_id === $referenceId;
    }
}
