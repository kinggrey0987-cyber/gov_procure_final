<?php

namespace App\Services\Procurement;

use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Supplier
    {
        $this->assertProcurementActor($actor);

        $supplier = Supplier::query()->create([
            'supplier_name' => $payload['supplier_name'],
            'contact_person' => $payload['contact_person'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'address' => $payload['address'] ?? null,
            'status' => $payload['status'],
        ]);

        $this->audit($actor, 'CREATE', $supplier);

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Supplier $supplier, User $actor, array $payload): Supplier
    {
        $this->assertProcurementActor($actor);

        $supplier->fill([
            'supplier_name' => $payload['supplier_name'],
            'contact_person' => $payload['contact_person'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'address' => $payload['address'] ?? null,
            'status' => $payload['status'],
        ])->save();

        $this->audit($actor, 'UPDATE', $supplier);

        return $supplier;
    }

    private function assertProcurementActor(User $actor): void
    {
        if (! $actor->isProcurement()) {
            throw ValidationException::withMessages([
                'role' => 'Only Procurement Unit users can perform this action.',
            ]);
        }
    }

    private function audit(User $actor, string $actionType, Supplier $supplier): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Procurement.Supplier',
            actionType: $actionType,
            referenceType: Supplier::class,
            referenceId: (int) $supplier->supplier_id,
            details: [
                'supplier_name' => $supplier->supplier_name,
                'status' => $supplier->status,
            ],
            logDomain: 'BUSINESS',
            officeId: $actor->office_id,
        );
    }
}
