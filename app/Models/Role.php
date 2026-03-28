<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected bool $codeImmutable = true;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (Role $role): void {
            if ($role->codeImmutable && $role->isDirty('code')) {
                throw new \LogicException('Role code is immutable and cannot be changed.');
            }
        });

        static::deleting(function (Role $role): void {
            $canonicalCodes = array_map(
                static fn (UserRole $enum): string => $enum->value,
                UserRole::cases()
            );

            if (in_array($role->code, $canonicalCodes, true)) {
                throw new \LogicException('Canonical roles cannot be deleted.');
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
