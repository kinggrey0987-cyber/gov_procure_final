<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Office\SupplyRequestService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeniedRequestWorkflowStrictBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $gsoUser;
    private User $procUser;
    private User $officeUser;
    private Office $office;
    private Supplier $supplier;
    private Ppmp $ppmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $this->office = Office::query()->create(['name' => 'Mayor\'s Office', 'code' => 'MO', 'status' => 'ACTIVE']);
        $gsoOffice = Office::query()->create(['name' => 'General Services Office', 'code' => 'GSO', 'status' => 'ACTIVE']);

        $this->gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $gsoOffice->id]);
        $this->procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $gsoOffice->id]);
        $this->officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $this->office->id]);

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'GovSupply Prime Vendor',
            'contact_person' => 'Juan Dela Cruz',
            'contact_number' => '09123456789',
            'status' => 'ACTIVE',
        ]);

        $this->ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'fiscal_year' => (int) now()->year,
            'status' => 'APPROVED',
            'prepared_by' => $this->officeUser->id,
            'date_prepared' => now()->toDateString(),
        ]);
    }

    public function test_gso_can_formally_disapprove_office_supply_request_with_remarks(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0001',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Office equipment upgrade',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $response = $this->actingAs($this->gsoUser)
            ->post(route('inventory.office-supply-requests.reject', $supplyRequest->supply_request_id), [
                'remarks' => 'Disapproved due to insufficient justification and missing quarterly allocation.',
            ]);

        $response->assertRedirect(route('inventory.office-supply-requests.index'));
        $response->assertSessionHas('success', 'Supply request disapproved successfully.');

        $supplyRequest->refresh();
        $this->assertSame('REJECTED', (string) $supplyRequest->status);
        $this->assertSame('Disapproved due to insufficient justification and missing quarterly allocation.', (string) $supplyRequest->review_notes);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'old_status' => 'SUBMITTED',
            'new_status' => 'REJECTED',
            'changed_by' => $this->gsoUser->id,
        ]);

        $this->assertDatabaseHas('approval_history', [
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'status' => 'REJECTED',
            'approved_by' => $this->gsoUser->id,
        ]);
    }

    public function test_disapproved_supply_request_cannot_proceed_to_distribution(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0002',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'REJECTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Denied requisition',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
            'review_notes' => 'Denied by GSO.',
        ]);

        // Controller route block test
        $response = $this->actingAs($this->gsoUser)
            ->post(route('inventory.office-supply-requests.process-distribution', $supplyRequest->supply_request_id));

        $response->assertRedirect(route('inventory.office-supply-requests.index'));
        $response->assertSessionHas('error', 'Disapproved or denied supply requests cannot proceed to warehouse distribution.');

        // Service level direct call block test
        $distService = app(DistributionRecordService::class);
        $this->expectException(ValidationException::class);

        $distService->createRecord($this->gsoUser, [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'office_id' => (int) $this->office->id,
        ]);
    }

    public function test_disapproved_supply_request_cannot_be_queued_for_procurement(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0003',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'REJECTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Denied requisition',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        // Controller route block test
        $response = $this->actingAs($this->gsoUser)
            ->post(route('inventory.office-supply-requests.queue', $supplyRequest->supply_request_id));

        $response->assertRedirect(route('inventory.office-supply-requests.index'));
        $response->assertSessionHas('error', 'Disapproved or denied supply requests cannot be queued for procurement.');

        // Service level direct call block test
        $prService = app(PurchaseRequestService::class);
        $this->expectException(ValidationException::class);

        $prService->queueFromSupplyRequest($this->gsoUser, [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
        ]);
    }

    public function test_disapproved_supply_request_cannot_become_purchase_request(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0004',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'REJECTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Denied requisition',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $prService = app(PurchaseRequestService::class);
        $this->expectException(ValidationException::class);

        $prService->createFromSupplyRequest($this->procUser, [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
        ]);
    }

    public function test_disapproved_supply_request_cannot_be_consolidated(): void
    {
        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0005',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Valid request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0006',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'REJECTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Denied request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $prService = app(PurchaseRequestService::class);
        $this->expectException(ValidationException::class);

        $prService->queueConsolidatedFromOffice(
            actor: $this->gsoUser,
            officeId: (int) $this->office->id,
            supplyRequestIds: [(int) $sr1->supply_request_id, (int) $sr2->supply_request_id],
        );
    }

    public function test_disapproved_supply_request_cannot_be_modified(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0007',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'REJECTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'ppmp_id' => $this->ppmp->ppmp_id,
            'purpose' => 'Denied request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $srService = app(SupplyRequestService::class);
        $this->expectException(ValidationException::class);

        $srService->update($supplyRequest, $this->officeUser, [
            'ppmp_id' => (int) $this->ppmp->ppmp_id,
            'purpose' => 'Attempting to edit disapproved request',
            'needed_by_date' => now()->addDays(20)->toDateString(),
            'request_category' => 'OFFICE_SUPPLIES',
            'priority_level' => 'NORMAL',
        ]);
    }

    public function test_rejected_purchase_request_cannot_be_moved_to_processing(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0008',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Linked request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-0001',
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->procUser->id,
            'status' => 'REJECTED',
            'remarks' => 'Rejected during procurement evaluation.',
        ]);

        $prService = app(PurchaseRequestService::class);
        $this->expectException(ValidationException::class);

        $prService->process($purchaseRequest, $this->procUser);
    }

    public function test_rejected_purchase_request_cannot_create_purchase_order(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0009',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Linked request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-0002',
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->procUser->id,
            'status' => 'REJECTED',
            'remarks' => 'Rejected during procurement evaluation.',
        ]);

        $poService = app(PurchaseOrderService::class);
        $this->expectException(ValidationException::class);

        $poService->create($this->procUser, [
            'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
            'supplier_id' => (int) $this->supplier->supplier_id,
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
        ]);
    }

    public function test_rejected_purchase_request_cannot_have_items_added(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MO-2026-0010',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Linked request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-0003',
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->procUser->id,
            'status' => 'REJECTED',
            'remarks' => 'Rejected during procurement evaluation.',
        ]);

        $prService = app(PurchaseRequestService::class);
        $this->expectException(ValidationException::class);

        $prService->addItem($purchaseRequest, $this->procUser, [
            'item_id' => 1,
            'quantity_requested' => 10,
        ]);
    }
}
