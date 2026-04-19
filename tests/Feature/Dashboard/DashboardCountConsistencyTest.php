<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Notification;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardCountConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;
    private int $recordSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_office_dashboard_counts_are_scoped_and_consistent(): void
    {
        $officeA = $this->createOffice('OFF_A', 'Office A');
        $officeB = $this->createOffice('OFF_B', 'Office B');
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $officeA, 'Office A User');
        $procUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $officeA, 'Proc User');

        $sr1 = $this->createSupplyRequest($officeA, $officeUser, 'DRAFT');
        $sr2 = $this->createSupplyRequest($officeA, $officeUser, 'SUBMITTED');
        $sr3 = $this->createSupplyRequest($officeA, $officeUser, 'SUBMITTED');
        $sr4 = $this->createSupplyRequest($officeA, $officeUser, 'SUBMITTED');
        $sr5 = $this->createSupplyRequest($officeA, $officeUser, 'SUBMITTED');

        PurchaseRequest::query()->create([
            'supply_request_id' => (int) $sr3->supply_request_id,
            'office_id' => (int) $officeA->id,
            'requested_by' => (int) $officeUser->id,
            'status' => 'OPEN',
            'remarks' => 'Queued for procurement',
        ]);

        $delivery = $this->createDeliveryContext($officeA, $officeUser, $procUser, $sr3);

        DistributionRecord::query()->create([
            'distribution_code' => $this->nextCode('DIST'),
            'supply_request_id' => (int) $sr4->supply_request_id,
            'delivery_id' => (int) $delivery->delivery_id,
            'office_id' => (int) $officeA->id,
            'prepared_by' => (int) $officeUser->id,
            'received_by' => null,
            'release_date' => now()->toDateString(),
            'storage_location' => 'GSO Rack A',
            'status' => 'RELEASED',
            'notes' => 'Ready for office pickup',
        ]);

        DistributionRecord::query()->create([
            'distribution_code' => $this->nextCode('DIST'),
            'supply_request_id' => (int) $sr5->supply_request_id,
            'delivery_id' => (int) $delivery->delivery_id,
            'office_id' => (int) $officeA->id,
            'prepared_by' => (int) $officeUser->id,
            'received_by' => (int) $officeUser->id,
            'release_date' => now()->toDateString(),
            'storage_location' => 'GSO Rack B',
            'status' => 'RECEIVED',
            'notes' => 'Already received',
        ]);

        $otherOfficeUser = $this->createUser(UserRole::OFFICE_USER, $officeB, 'Office B User');
        $otherOfficeRequest = $this->createSupplyRequest($officeB, $otherOfficeUser, 'SUBMITTED');
        PurchaseRequest::query()->create([
            'supply_request_id' => (int) $otherOfficeRequest->supply_request_id,
            'office_id' => (int) $officeB->id,
            'requested_by' => (int) $otherOfficeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);

        $response = $this->actingAs($officeUser)->get(route('office.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Office/Tracking')
            ->where('summary.total_requests', 5)
            ->where('summary.pending_requests', 2)
            ->where('summary.processed_requests', 3)
            ->where('summary.ready_for_claim', 1)
            ->where('summary.completed_requests', 1)
        );
    }

    public function test_procurement_dashboard_counts_use_normalized_status_groups(): void
    {
        $office = $this->createOffice('PROC_OFC', 'Proc Office');
        $procUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office, 'Proc Officer');
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office, 'Office Requester');

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Proc Test Supplier',
            'contact_person' => 'Proc Contact',
            'email' => 'proc.supplier@example.test',
            'phone' => '09000000001',
            'address' => 'Procurement Street',
            'status' => 'ACTIVE',
        ]);

        $srOpen = $this->createSupplyRequest($office, $officeUser, 'SUBMITTED');
        $srProcessing = $this->createSupplyRequest($office, $officeUser, 'SUBMITTED');
        $srProcessing2 = $this->createSupplyRequest($office, $officeUser, 'SUBMITTED');

        $prOpen = PurchaseRequest::query()->create([
            'supply_request_id' => (int) $srOpen->supply_request_id,
            'office_id' => (int) $office->id,
            'requested_by' => (int) $officeUser->id,
            'status' => 'OPEN',
            'remarks' => null,
        ]);

        $prProcessing = PurchaseRequest::query()->create([
            'supply_request_id' => (int) $srProcessing->supply_request_id,
            'office_id' => (int) $office->id,
            'requested_by' => (int) $officeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);

        $prProcessing2 = PurchaseRequest::query()->create([
            'supply_request_id' => (int) $srProcessing2->supply_request_id,
            'office_id' => (int) $office->id,
            'requested_by' => (int) $officeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);

        PurchaseOrder::query()->create([
            'purchase_request_id' => (int) $prProcessing->purchase_request_id,
            'supply_request_id' => (int) $srProcessing->supply_request_id,
            'supplier_id' => (int) $supplier->supplier_id,
            'created_by' => (int) $procUser->id,
            'po_number' => $this->nextCode('PO'),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 5000,
            'terms_notes' => null,
            'remarks' => null,
        ]);

        PurchaseOrder::query()->create([
            'purchase_request_id' => (int) $prProcessing2->purchase_request_id,
            'supply_request_id' => (int) $srProcessing2->supply_request_id,
            'supplier_id' => (int) $supplier->supplier_id,
            'created_by' => (int) $procUser->id,
            'po_number' => $this->nextCode('PO'),
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'COMPLETED',
            'expected_delivery_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 7500,
            'terms_notes' => null,
            'remarks' => null,
        ]);

        $response = $this->actingAs($procUser)->get(route('procurement.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Procurement/Tracking')
            ->where('summary.pending_requests', 1)
            ->where('summary.processing_requests', 2)
            ->where('summary.ongoing_procurement', 1)
            ->where('summary.completed_procurements', 1)
        );
    }

    public function test_inventory_dashboard_counts_include_active_claim_statuses(): void
    {
        $office = $this->createOffice('GSO_OFC', 'GSO Office');
        $gsoUser = $this->createUser(UserRole::GSO_OFFICER, $office, 'GSO Officer');
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office, 'Office Receiver');
        $procUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office, 'Proc User 2');

        $category = ItemCategory::query()->create([
            'category_name' => 'General Supplies',
            'standard_delivery_days' => 7,
            'description' => null,
            'status' => 'active',
        ]);

        $itemA = Item::query()->create([
            'item_category_id' => (int) $category->item_category_id,
            'item_name' => 'Inventory Item A',
            'description' => null,
            'default_unit' => 'pcs',
            'status' => 'active',
        ]);

        $itemB = Item::query()->create([
            'item_category_id' => (int) $category->item_category_id,
            'item_name' => 'Inventory Item B',
            'description' => null,
            'default_unit' => 'pcs',
            'status' => 'active',
        ]);

        $inventoryA = Inventory::query()->create([
            'item_id' => (int) $itemA->item_id,
            'available_stock' => 5,
            'stock_on_hand' => 10,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 8,
            'last_updated' => now(),
        ]);

        $inventoryB = Inventory::query()->create([
            'item_id' => (int) $itemB->item_id,
            'available_stock' => 20,
            'stock_on_hand' => 20,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 8,
            'last_updated' => now(),
        ]);

        InventoryMovement::query()->insert([
            [
                'inventory_id' => (int) $inventoryA->inventory_id,
                'movement_type' => 'IN',
                'reference_type' => 'Test',
                'reference_id' => 1,
                'quantity' => 5,
                'performed_by' => (int) $gsoUser->id,
                'last_updated' => now(),
                'movement_date' => now()->toDateString(),
                'remarks' => null,
            ],
            [
                'inventory_id' => (int) $inventoryB->inventory_id,
                'movement_type' => 'OUT',
                'reference_type' => 'Test',
                'reference_id' => 2,
                'quantity' => 2,
                'performed_by' => (int) $gsoUser->id,
                'last_updated' => now(),
                'movement_date' => now()->toDateString(),
                'remarks' => null,
            ],
        ]);

        $sr = $this->createSupplyRequest($office, $officeUser, 'SUBMITTED');
        $delivery = $this->createDeliveryContext($office, $officeUser, $procUser, $sr);

        $releasedRecord = DistributionRecord::query()->create([
            'distribution_code' => $this->nextCode('DIST'),
            'supply_request_id' => (int) $sr->supply_request_id,
            'delivery_id' => (int) $delivery->delivery_id,
            'office_id' => (int) $office->id,
            'prepared_by' => (int) $gsoUser->id,
            'received_by' => null,
            'release_date' => now()->toDateString(),
            'storage_location' => 'Shelf A',
            'status' => 'RELEASED',
            'notes' => null,
        ]);

        DistributionRecord::query()->create([
            'distribution_code' => $this->nextCode('DIST'),
            'supply_request_id' => (int) $sr->supply_request_id,
            'delivery_id' => (int) $delivery->delivery_id,
            'office_id' => (int) $office->id,
            'prepared_by' => (int) $gsoUser->id,
            'received_by' => (int) $officeUser->id,
            'release_date' => now()->toDateString(),
            'storage_location' => 'Shelf B',
            'status' => 'RECEIVED',
            'notes' => null,
        ]);

        Claim::query()->insert([
            [
                'claim_code' => $this->nextCode('CLM'),
                'distribution_record_id' => (int) $releasedRecord->distribution_record_id,
                'claimed_by' => (int) $officeUser->id,
                'office_id' => (int) $office->id,
                'claim_type' => 'MISSING_ITEM',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office A',
                'remarks' => null,
                'status' => 'OPEN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'claim_code' => $this->nextCode('CLM'),
                'distribution_record_id' => (int) $releasedRecord->distribution_record_id,
                'claimed_by' => (int) $officeUser->id,
                'office_id' => (int) $office->id,
                'claim_type' => 'DAMAGED_ITEM',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office A',
                'remarks' => null,
                'status' => 'IN_REVIEW',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'claim_code' => $this->nextCode('CLM'),
                'distribution_record_id' => (int) $releasedRecord->distribution_record_id,
                'claimed_by' => (int) $officeUser->id,
                'office_id' => (int) $office->id,
                'claim_type' => 'MISSING_ITEM',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office A',
                'remarks' => null,
                'status' => 'RESOLVED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($gsoUser)->get(route('inventory.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Tracking')
            ->where('summary.inventory_count', 2)
            ->where('summary.low_stock_count', 1)
            ->where('summary.movement_count', 2)
            ->where('summary.released_distribution_count', 1)
            ->where('summary.received_distribution_count', 1)
            ->where('summary.open_claim_count', 2)
        );
    }

    public function test_executive_dashboard_counts_use_central_status_groups(): void
    {
        $officeA = $this->createOffice('EXEC_A', 'Exec Office A');
        $officeB = $this->createOffice('EXEC_B', 'Exec Office B');

        $execUser = $this->createUser(UserRole::EXECUTIVE_OFFICER, $officeA, 'Executive');
        $preparer = $this->createUser(UserRole::OFFICE_USER, $officeA, 'Preparer');

        Anomaly::query()->insert([
            [
                'anomaly_type' => 'TEST_OPEN',
                'reference_type' => 'TEST',
                'reference_id' => 1,
                'item_id' => null,
                'office_id' => (int) $officeA->id,
                'detected_value' => null,
                'expected_value' => null,
                'threshold_value' => null,
                'z_score' => null,
                'detection_method' => null,
                'severity' => 'HIGH',
                'status' => 'OPEN',
                'detected_at' => now(),
                'remarks' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anomaly_type' => 'TEST_REVIEW',
                'reference_type' => 'TEST',
                'reference_id' => 2,
                'item_id' => null,
                'office_id' => (int) $officeB->id,
                'detected_value' => null,
                'expected_value' => null,
                'threshold_value' => null,
                'z_score' => null,
                'detection_method' => null,
                'severity' => 'LOW',
                'status' => 'IN_REVIEW',
                'detected_at' => now(),
                'remarks' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Alert::query()->insert([
            [
                'alert_type' => 'TEST_ALERT_OPEN',
                'severity' => 'HIGH',
                'anomaly_id' => null,
                'supply_request_id' => null,
                'purchase_order_id' => null,
                'supplier_id' => null,
                'office_id' => (int) $officeA->id,
                'alert_message' => 'Open alert',
                'detected_at' => now(),
                'reviewed_by' => null,
                'status' => 'OPEN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'alert_type' => 'TEST_ALERT_REVIEW',
                'severity' => 'LOW',
                'anomaly_id' => null,
                'supply_request_id' => null,
                'purchase_order_id' => null,
                'supplier_id' => null,
                'office_id' => (int) $officeB->id,
                'alert_message' => 'In review alert',
                'detected_at' => now(),
                'reviewed_by' => null,
                'status' => 'IN_REVIEW',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Ppmp::query()->insert([
            $this->ppmpRow($officeA, $preparer, 'SUBMITTED'),
            $this->ppmpRow($officeA, $preparer, 'APPROVED'),
            $this->ppmpRow($officeA, $preparer, 'RETURNED_FOR_REVISION'),
            $this->ppmpRow($officeB, $preparer, 'REJECTED'),
            $this->ppmpRow($officeB, $preparer, 'DRAFT'),
        ]);

        $response = $this->actingAs($execUser)->get(route('executive.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Executive/Dashboard/Index')
            ->where('summary.open_anomaly_count', 1)
            ->where('summary.in_review_anomaly_count', 1)
            ->where('summary.open_alert_count', 1)
            ->where('summary.in_review_alert_count', 1)
            ->where('approvals.pending_ppmp_approvals', 1)
            ->where('approvals.ppmp_workflow_total', 4)
            ->where('summary.cross_office_anomaly_count', 2)
        );
    }

    public function test_admin_dashboard_counts_remain_technical_and_global(): void
    {
        $adminOffice = $this->createOffice('ADM_OFC', 'Admin Office');
        $adminUser = $this->createUser(UserRole::ADMIN, $adminOffice, 'Admin User');

        $otherOffice = $this->createOffice('OTH_OFC', 'Other Office');
        $this->createUser(UserRole::OFFICE_USER, $otherOffice, 'Other Office User');
        $this->createUser(UserRole::PROCUREMENT_OFFICER, $otherOffice, 'Other Proc User');

        Role::query()->firstOrCreate(
            ['code' => 'INACTIVE_TEST_ROLE'],
            ['name' => 'Inactive Test Role', 'description' => null, 'is_active' => false]
        );

        Notification::query()->insert([
            [
                'user_id' => (int) $adminUser->id,
                'title' => 'Unread',
                'message' => 'Unread message',
                'notification_type' => 'TEST',
                'reference_type' => null,
                'reference_id' => null,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => (int) $adminUser->id,
                'title' => 'Read',
                'message' => 'Read message',
                'notification_type' => 'TEST',
                'reference_type' => null,
                'reference_id' => null,
                'is_read' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($adminUser)->get(route('admin-tech.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/AdminDashboard')
            ->where('summary.total_users', 3)
            ->where('summary.office_count', 2)
            ->where('summary.active_role_count', 3)
            ->where('summary.unread_notification_count', 1)
        );
    }

    private function createOffice(string $code, string $name): Office
    {
        return Office::query()->create([
            'code' => $code,
            'name' => $name,
            'description' => null,
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office, string $name): User
    {
        $resolvedRole = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for dashboard tests',
                'is_active' => true,
            ],
        );

        $this->userSequence++;

        return User::query()->create([
            'name' => $name,
            'email' => sprintf('user%d@example.test', $this->userSequence),
            'emp_id' => sprintf('EMP-%05d', $this->userSequence),
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'role_id' => (int) $resolvedRole->id,
            'office_id' => (int) $office->id,
            'email_verified_at' => now(),
        ]);
    }

    private function createSupplyRequest(Office $office, User $requester, string $status): SupplyRequest
    {
        return SupplyRequest::query()->create([
            'request_code' => $this->nextCode('SR'),
            'office_id' => (int) $office->id,
            'requested_by' => (int) $requester->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Dashboard count test',
            'priority_level' => 'NORMAL',
            'status' => $status,
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    private function createDeliveryContext(Office $office, User $officeUser, User $procUser, SupplyRequest $supplyRequest): Delivery
    {
        $supplier = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Dashboard Test Supplier'],
            [
                'contact_person' => 'Supplier Contact',
                'email' => 'supplier@example.test',
                'phone' => '09000000000',
                'address' => 'Test Address',
                'status' => 'ACTIVE',
            ],
        );

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'office_id' => (int) $office->id,
            'requested_by' => (int) $officeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'supplier_id' => (int) $supplier->supplier_id,
            'created_by' => (int) $procUser->id,
            'po_number' => $this->nextCode('PO'),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'total_amount' => 1000,
            'terms_notes' => null,
            'remarks' => null,
        ]);

        return Delivery::query()->create([
            'delivery_code' => $this->nextCode('DEL'),
            'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
            'supplier_id' => (int) $supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->toDateString(),
            'received_by' => (int) $officeUser->id,
            'receipt_status' => 'RECEIVED',
            'remarks' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ppmpRow(Office $office, User $preparer, string $status): array
    {
        return [
            'office_id' => (int) $office->id,
            'prepared_by' => (int) $preparer->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => $status,
            'total_estimated_budget' => 1000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => $status === 'APPROVED' ? now()->toDateString() : null,
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function nextCode(string $prefix): string
    {
        $this->recordSequence++;

        return sprintf('%s-%06d', $prefix, $this->recordSequence);
    }
}
