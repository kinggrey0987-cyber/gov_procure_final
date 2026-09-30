<?php

namespace App\Models;

 use Illuminate\Database\Eloquent\Builder;
 use Illuminate\Database\Eloquent\Factories\HasFactory;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;
 use Illuminate\Database\Eloquent\Relations\HasMany;
 use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $primaryKey = 'purchase_request_id';

    public function getRouteKeyName(): string
    {
        return 'purchase_request_id';
    }

     protected $fillable = [
         'pr_number',
         'supply_request_id',
         'office_id',
         'requested_by',
         'source_delivery_id',
         'source_supply_request_id',
         'source_claim_id',
         'source_type',
         'status',
         'remarks',
     ];

     protected $casts = [
         'supply_request_id' => 'integer',
         'office_id' => 'integer',
         'requested_by' => 'integer',
         'source_delivery_id' => 'integer',
         'source_supply_request_id' => 'integer',
         'source_claim_id' => 'integer',
     ];

     protected $appends = [
         'priority_level',
         'is_zero_cost_replacement',
         'is_claim_replacement',
     ];

     public function getIsZeroCostReplacementAttribute(): bool
     {
         if ((string) ($this->source_type ?? '') === 'PARTIAL_DELIVERY_FOLLOW_UP') {
             return true;
         }

         if ($this->is_claim_replacement) {
             return false;
         }

         return (int) ($this->source_delivery_id ?? 0) > 0;
     }

     public function getIsClaimReplacementAttribute(): bool
     {
         if ((string) ($this->source_type ?? '') === 'PARTIAL_DELIVERY_FOLLOW_UP') {
             return false;
         }

         return (string) ($this->source_type ?? '') === 'CLAIM_REPLACEMENT_FOLLOW_UP'
             || (int) ($this->source_claim_id ?? 0) > 0;
     }

     public function getPriorityLevelAttribute(): string
     {
         if ($this->relationLoaded('supplyRequest') && $this->supplyRequest) {
             if (strtoupper((string) ($this->supplyRequest->priority_level ?? '')) === 'URGENT') {
                 return 'URGENT';
             }
         } elseif ($this->supply_request_id) {
             $level = SupplyRequest::query()
                 ->where('supply_request_id', (int) $this->supply_request_id)
                 ->value('priority_level');

             if (strtoupper((string) ($level ?? '')) === 'URGENT') {
                 return 'URGENT';
             }
         }

         if (! empty($this->remarks) && (str_contains($this->remarks, 'Consolidated PR') || preg_match('/SR-[A-Z0-9-]+/', $this->remarks))) {
             if (preg_match_all('/SR-[A-Z0-9-]+/', $this->remarks, $matches)) {
                 $hasUrgent = SupplyRequest::query()
                     ->whereIn('request_code', $matches[0])
                     ->whereRaw("UPPER(priority_level) = 'URGENT'")
                     ->exists();

                 if ($hasUrgent) {
                     return 'URGENT';
                 }
             }
         }

         return 'NORMAL';
     }

     public function getPrNumberAttribute(?string $value): string
     {
         if (! empty($value)) {
             return $value;
         }

         $date = $this->created_at ?? now();

         return sprintf('PR-%s-%04d', $date->format('Y-m'), (int) $this->purchase_request_id);
     }

     public function supplyRequest(): BelongsTo
     {
         return $this->belongsTo(SupplyRequest::class, 'supply_request_id', 'supply_request_id');
     }

     public function office(): BelongsTo
     {
         return $this->belongsTo(Office::class);
     }

     public function requester(): BelongsTo
     {
         return $this->belongsTo(User::class, 'requested_by');
     }

     public function sourceDelivery(): BelongsTo
     {
         return $this->belongsTo(Delivery::class, 'source_delivery_id', 'delivery_id');
     }

     public function sourceClaim(): BelongsTo
     {
         return $this->belongsTo(Claim::class, 'source_claim_id', 'claims_id');
     }

     public function sourceSupplyRequest(): BelongsTo
     {
         return $this->belongsTo(SupplyRequest::class, 'source_supply_request_id', 'supply_request_id');
     }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function approvalHistory(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'reference', 'reference_type', 'reference_id');
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['OPEN', 'RETURNED_FOR_REVISION']);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'PROCESSING');
    }
}
