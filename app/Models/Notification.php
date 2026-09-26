<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    protected $primaryKey = 'notification_id';

    public function getRouteKeyName(): string
    {
        return 'notification_id';
    }

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'notification_type',
        'reference_type',
        'reference_id',
        'is_read',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'reference_id' => 'integer',
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeByType(Builder $query, string $notificationType): Builder
    {
        return $query->where('notification_type', $notificationType);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function getMessageAttribute(?string $value): string
    {
        $message = (string) ($value ?? '');

        return (string) preg_replace_callback(
            '/Anomaly\s+([A-Z0-9_]+)\s+was\s+detected/i',
            fn ($matches) => Anomaly::humanizeType($matches[1]) . ' was detected',
            $message
        );
    }
}
