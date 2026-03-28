<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'office_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'office_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function governanceNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationsInbox(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'approved_by');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'changed_by');
    }

    public function preparedPpmps(): HasMany
    {
        return $this->hasMany(Ppmp::class, 'prepared_by');
    }

    public function approvedPpmps(): HasMany
    {
        return $this->hasMany(Ppmp::class, 'approved_by');
    }

    public function requestedSupplyRequests(): HasMany
    {
        return $this->hasMany(SupplyRequest::class, 'requested_by');
    }

    public function approvedSupplyRequests(): HasMany
    {
        return $this->hasMany(SupplyRequest::class, 'approved_by');
    }

    public function requestedPurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requested_by', 'id');
    }

    public function createdPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by', 'id');
    }

    public function receivedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'received_by', 'id');
    }

    public function verifiedDeliveries(): HasMany
    {
        return $this->hasMany(DeliveryVerification::class, 'verified_by', 'id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'performed_by', 'id');
    }

    public function preparedDistributionRecords(): HasMany
    {
        return $this->hasMany(DistributionRecord::class, 'prepared_by', 'id');
    }

    public function receivedDistributionRecords(): HasMany
    {
        return $this->hasMany(DistributionRecord::class, 'received_by', 'id');
    }

    public function receivedDistributionItems(): HasMany
    {
        return $this->hasMany(DistributionItem::class, 'received_by', 'id');
    }

    public function filedClaims(): HasMany
    {
        return $this->hasMany(Claim::class, 'claimed_by', 'id');
    }

    public function reviewedAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'reviewed_by', 'id');
    }

    public function publishedDisclosures(): HasMany
    {
        return $this->hasMany(PublicDisclosure::class, 'published_by', 'id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by', 'id');
    }

    public function roleCode(): ?string
    {
        return $this->role?->code;
    }

    public function hasRole(UserRole|string $role): bool
    {
        $roleCode = $role instanceof UserRole ? $role->value : $role;

        return $this->roleCode() === $roleCode;
    }

    /**
     * @param  array<int, UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    public function isProcurement(): bool
    {
        return $this->hasRole(UserRole::PROCUREMENT_OFFICER);
    }

    public function isGsoOfficer(): bool
    {
        return $this->hasRole(UserRole::GSO_OFFICER);
    }

    public function isOfficeUser(): bool
    {
        return $this->hasRole(UserRole::OFFICE_USER);
    }

    public function isExecutive(): bool
    {
        return $this->hasRole(UserRole::EXECUTIVE_OFFICER);
    }

    public function isPublicUser(): bool
    {
        return $this->hasRole(UserRole::PUBLIC);
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }
}
