<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Office;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditHistoryUiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $officeA;
    protected Office $officeB;
    protected User $admin;
    protected User $executive;
    protected User $officeUserA;
    protected User $officeUserB;
    protected User $procurement;
    protected User $gso;
    protected User $publicUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->officeA = $this->createOffice('Audit Office A', 'AUD_A');
        $this->officeB = $this->createOffice('Audit Office B', 'AUD_B');

        $this->admin = $this->createUser(UserRole::ADMIN, $this->officeA);
        $this->executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->officeA);
        $this->officeUserA = $this->createUser(UserRole::OFFICE_USER, $this->officeA);
        $this->officeUserB = $this->createUser(UserRole::OFFICE_USER, $this->officeB);
        $this->procurement = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->officeA);
        $this->gso = $this->createUser(UserRole::GSO_OFFICER, $this->officeA);
        $this->publicUser = $this->createUser(UserRole::PUBLIC, $this->officeA);
    }

    public function test_admin_can_access_technical_audit_only_and_exec_can_access_business_audit_only(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->admin->id,
            'office_id' => $this->officeA->id,
            'log_domain' => 'TECHNICAL',
            'module_name' => 'Governance.User',
            'action_type' => 'UPDATE',
            'reference_type' => User::class,
            'reference_id' => $this->admin->id,
            'details' => ['note' => 'technical'],
        ]);

        AuditLog::query()->create([
            'user_id' => $this->executive->id,
            'office_id' => $this->officeA->id,
            'log_domain' => 'BUSINESS',
            'module_name' => 'Executive.Alert',
            'action_type' => 'UPDATE_STATUS',
            'reference_type' => User::class,
            'reference_id' => $this->executive->id,
            'details' => ['note' => 'business'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin-tech.audit.technical'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminTech/Audit/Technical')
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.log_domain', 'TECHNICAL')
            );

        $this->actingAs($this->admin)
            ->get(route('executive.audit.business'))
            ->assertForbidden();

        $this->actingAs($this->executive)
            ->get(route('executive.audit.business'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Audit/Business')
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.log_domain', 'BUSINESS')
            );

        $this->actingAs($this->executive)
            ->get(route('admin-tech.audit.technical'))
            ->assertForbidden();
    }

    public function test_wrong_roles_and_public_are_denied_from_audit_centers(): void
    {
        $this->actingAs($this->officeUserA)->get(route('admin-tech.audit.technical'))->assertForbidden();
        $this->actingAs($this->officeUserA)->get(route('executive.audit.business'))->assertForbidden();
        $this->actingAs($this->procurement)->get(route('admin-tech.audit.technical'))->assertForbidden();
        $this->actingAs($this->gso)->get(route('executive.audit.business'))->assertForbidden();
        $this->actingAs($this->publicUser)->get(route('admin-tech.audit.technical'))->assertForbidden();
        $this->actingAs($this->publicUser)->get(route('executive.audit.business'))->assertForbidden();
    }

    public function test_office_user_sees_history_only_for_own_office_supply_request(): void
    {
        $ownRequest = $this->createSupplyRequest($this->officeA, $this->officeUserA);
        $otherRequest = $this->createSupplyRequest($this->officeB, $this->officeUserB);

        StatusHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => $ownRequest->supply_request_id,
            'old_status' => 'DRAFT',
            'new_status' => 'SUBMITTED',
            'changed_by' => $this->officeUserA->id,
            'remarks' => 'Submitted by office user',
            'changed_at' => now(),
        ]);

        ApprovalHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => $ownRequest->supply_request_id,
            'approved_by' => $this->executive->id,
            'status' => 'APPROVED',
            'remarks' => 'Approved for test',
            'approved_at' => now(),
        ]);

        $this->actingAs($this->officeUserA)
            ->get(route('office.supply-requests.show', $ownRequest->supply_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Show')
                ->has('statusHistory', 1)
                ->has('approvalHistory', 1)
            );

        $this->actingAs($this->officeUserA)
            ->get(route('office.supply-requests.show', $otherRequest->supply_request_id))
            ->assertForbidden();
    }

    public function test_procurement_and_gso_see_history_only_for_authorized_records(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurement, $this->officeUserA);

        $claim = $this->createClaim($this->officeA, $this->gso, $purchaseOrder, $this->officeUserA);

        StatusHistory::query()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->purchase_order_id,
            'old_status' => 'DRAFT',
            'new_status' => 'PLACED',
            'changed_by' => $this->procurement->id,
            'remarks' => 'Placed for testing',
            'changed_at' => now(),
        ]);

        ApprovalHistory::query()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->purchase_order_id,
            'approved_by' => $this->executive->id,
            'status' => 'PLACED',
            'remarks' => 'Recorded placement',
            'approved_at' => now(),
        ]);

        StatusHistory::query()->create([
            'reference_type' => Claim::class,
            'reference_id' => $claim->claims_id,
            'old_status' => 'OPEN',
            'new_status' => 'IN_REVIEW',
            'changed_by' => $this->gso->id,
            'remarks' => 'Review started',
            'changed_at' => now(),
        ]);

        $this->actingAs($this->procurement)
            ->get(route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->has('statusHistory', 1)
                ->has('approvalHistory', 1)
            );

        $this->actingAs($this->gso)
            ->get(route('inventory.claims.show', $claim->claims_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Claim/Show')
                ->has('statusHistory', 1)
            );

        $this->actingAs($this->gso)
            ->get(route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id))
            ->assertForbidden();

        $this->actingAs($this->procurement)
            ->get(route('inventory.claims.show', $claim->claims_id))
            ->assertForbidden();
    }

    public function test_executive_disclosure_page_receives_history_timelines_and_public_cannot_access_it(): void
    {
        $disclosure = PublicDisclosure::query()->create([
            'title' => 'History Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Timeline test',
            'published_by' => null,
            'published_at' => null,
            'file_path' => null,
            'status' => 'DRAFT',
        ]);

        StatusHistory::query()->create([
            'reference_type' => PublicDisclosure::class,
            'reference_id' => $disclosure->disclosure_id,
            'old_status' => 'DRAFT',
            'new_status' => 'PUBLISHED',
            'changed_by' => $this->executive->id,
            'remarks' => 'Published for test',
            'changed_at' => now(),
        ]);

        ApprovalHistory::query()->create([
            'reference_type' => PublicDisclosure::class,
            'reference_id' => $disclosure->disclosure_id,
            'approved_by' => $this->executive->id,
            'status' => 'APPROVED',
            'remarks' => 'Approved for publication',
            'approved_at' => now(),
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.disclosures.show', $disclosure->disclosure_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Disclosures/Show')
                ->has('statusHistory', 1)
                ->has('approvalHistory', 1)
            );

        $this->actingAs($this->publicUser)
            ->get(route('executive.disclosures.show', $disclosure->disclosure_id))
            ->assertForbidden();
    }

    private function createOffice(string $name, string $code): Office
    {
        return Office::query()->create([
            'name' => $name,
            'code' => $code,
            'description' => $name.' description',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Audit/history role',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }

    private function createSupplyRequest(Office $office, User $officeUser): SupplyRequest
    {
        return SupplyRequest::query()->create([
            'request_code' => 'SR-AUD-'.uniqid(),
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Audit history test request',
            'priority_level' => 'MEDIUM',
            'status' => 'DRAFT',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    private function createPurchaseOrder(Office $office, User $procurementUser, User $officeUser): PurchaseOrder
    {
        $supplyRequest = $this->createSupplyRequest($office, $officeUser);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementUser->id,
            'status' => 'PROCESSING',
            'remarks' => 'PO audit context',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Audit Supplier '.uniqid(),
            'contact_person' => 'Contact',
            'email' => uniqid('aud-supplier', true).'@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        return PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementUser->id,
            'po_number' => 'PO-AUD-'.uniqid(),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'DRAFT',
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 1000,
            'terms_notes' => null,
            'remarks' => null,
        ]);
    }

    private function createClaim(Office $office, User $gsoUser, PurchaseOrder $purchaseOrder, User $officeUser): Claim
    {
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-AUD-'.uniqid(),
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->toDateString(),
            'received_by' => $gsoUser->id,
            'receipt_status' => 'RECEIVED',
            'remarks' => null,
        ]);

        $distributionRecord = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-AUD-'.uniqid(),
            'supply_request_id' => $purchaseOrder->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'release_date' => now()->toDateString(),
            'storage_location' => 'Main Storage',
            'status' => 'RELEASED',
            'notes' => null,
        ]);

        return Claim::query()->create([
            'claim_code' => 'CLM-AUD-'.uniqid(),
            'distribution_record_id' => $distributionRecord->distribution_record_id,
            'claimed_by' => $gsoUser->id,
            'office_id' => $office->id,
            'claim_type' => 'SHORTAGE',
            'claim_date' => now()->toDateString(),
            'claim_location' => 'Municipal Warehouse',
            'remarks' => 'Claim for test',
            'status' => 'OPEN',
        ]);
    }
}
