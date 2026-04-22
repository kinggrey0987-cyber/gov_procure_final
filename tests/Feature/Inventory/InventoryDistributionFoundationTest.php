<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\ApprovalHistory;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryDistributionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $officeA;
    protected Office $officeB;
    protected User $gsoOfficer;
    protected User $officeUserA;
    protected User $officeUserB;
    protected User $procurementOfficer;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->officeA = $this->createOffice('Office A', 'OFF_A');
        $this->officeB = $this->createOffice('Office B', 'OFF_B');

        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->officeA);
        $this->officeUserA = $this->createUser(UserRole::OFFICE_USER, $this->officeA);
        $this->officeUserB = $this->createUser(UserRole::OFFICE_USER, $this->officeB);
        $this->procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->officeA);
        $this->adminUser = $this->createUser(UserRole::ADMIN, $this->officeA);
    }

    public function test_verified_delivery_sync_updates_inventory_and_logs_movement_once(): void
    {
        $context = $this->createVerifiedDeliveryContext($this->officeA, $this->officeUserA, $this->procurementOfficer, $this->gsoOfficer, 9001);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.inventory.sync-from-delivery'), [
                'delivery_id' => $context['delivery']->delivery_id,
            ])
            ->assertRedirect();

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.inventory.sync-from-delivery'), [
                'delivery_id' => $context['delivery']->delivery_id,
            ])
            ->assertRedirect();

        $inventory = Inventory::query()->where('item_id', 9001)->firstOrFail();

        $this->assertSame('4.00', (string) $inventory->available_stock);
        $this->assertSame('4.00', (string) $inventory->stock_on_hand);

        $this->assertSame(1, InventoryMovement::query()
            ->where('inventory_id', $inventory->inventory_id)
            ->where('movement_type', 'DELIVERY_RECEIPT')
            ->where('reference_type', Delivery::class)
            ->where('reference_id', $context['delivery']->delivery_id)
            ->count());

        $this->assertDatabaseHas('status_history', [
            'reference_type' => Inventory::class,
            'reference_id' => $inventory->inventory_id,
            'new_status' => 'LOW_STOCK',
        ]);
    }

    public function test_gso_can_release_stock_through_distribution_item_and_write_histories(): void
    {
        $context = $this->createVerifiedDeliveryContext($this->officeA, $this->officeUserA, $this->procurementOfficer, $this->gsoOfficer, 9002);

        Inventory::query()->create([
            'item_id' => 9002,
            'available_stock' => 10,
            'stock_on_hand' => 10,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 2,
            'last_updated' => now(),
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.distribution-records.store'), [
                'supply_request_id' => $context['supplyRequest']->supply_request_id,
                'delivery_id' => $context['delivery']->delivery_id,
                'office_id' => $this->officeA->id,
            ])
            ->assertRedirect();

        $record = DistributionRecord::query()->firstOrFail();

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.distribution-records.items.store', $record->distribution_record_id), [
                'item_id' => 9002,
                'item_name_snapshot' => 'Stock Item 9002',
                'quantity_released' => 3,
                'unit' => 'PCS',
            ])
            ->assertRedirect();

        $inventory = Inventory::query()->where('item_id', 9002)->firstOrFail();

        $this->assertSame('7.00', (string) $inventory->available_stock);
        $this->assertSame('7.00', (string) $inventory->stock_on_hand);
        $this->assertSame('3.00', (string) $inventory->released_quantity);

        $this->assertDatabaseHas('distribution_items', [
            'distribution_record_id' => $record->distribution_record_id,
            'item_id' => 9002,
            'quantity_released' => 3,
        ]);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => DistributionRecord::class,
            'reference_id' => $record->distribution_record_id,
            'new_status' => 'RELEASED',
        ]);

        $this->assertDatabaseHas('approval_history', [
            'reference_type' => DistributionRecord::class,
            'reference_id' => $record->distribution_record_id,
            'status' => 'RELEASED',
        ]);
    }

    public function test_distribution_item_release_rejects_quantity_above_available_stock(): void
    {
        $context = $this->createVerifiedDeliveryContext($this->officeA, $this->officeUserA, $this->procurementOfficer, $this->gsoOfficer, 9003);

        Inventory::query()->create([
            'item_id' => 9003,
            'available_stock' => 2,
            'stock_on_hand' => 2,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 1,
            'last_updated' => now(),
        ]);

        $record = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-TEST-0001',
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'delivery_id' => $context['delivery']->delivery_id,
            'office_id' => $this->officeA->id,
            'prepared_by' => $this->gsoOfficer->id,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.distribution-records.items.store', $record->distribution_record_id), [
                'item_id' => 9003,
                'item_name_snapshot' => 'Stock Item 9003',
                'quantity_released' => 5,
                'unit' => 'PCS',
            ])
            ->assertSessionHasErrors('quantity_released');
    }

    public function test_office_user_can_receive_own_distribution_but_not_other_office(): void
    {
        $own = $this->createReleasedDistributionRecordForOffice($this->officeA, 9004);
        $other = $this->createReleasedDistributionRecordForOffice($this->officeB, 9005);

        $this->actingAs($this->officeUserA)
            ->post(route('office.distribution-records.receive', $own->distribution_record_id))
            ->assertRedirect();

        $own->refresh();
        $this->assertSame('RECEIVED', $own->status);
        $this->assertSame($this->officeUserA->id, $own->received_by);

        $this->actingAs($this->officeUserA)
            ->post(route('office.distribution-records.receive', $other->distribution_record_id))
            ->assertForbidden();
    }

    public function test_admin_is_denied_from_inventory_distribution_and_claim_routes(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('inventory.inventory.index'))
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->get(route('inventory.distribution-records.index'))
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->get(route('inventory.claims.index'))
            ->assertForbidden();
    }

    public function test_claim_flow_enforces_office_scope_and_gso_status_processing(): void
    {
        $recordA = $this->createReleasedDistributionRecordForOffice($this->officeA, 9006);
        $recordB = $this->createReleasedDistributionRecordForOffice($this->officeB, 9007);

        $this->actingAs($this->officeUserA)
            ->post(route('office.claims.store'), [
                'distribution_record_id' => $recordA->distribution_record_id,
                'office_id' => $this->officeA->id,
                'claim_type' => 'MISSING_ITEM',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office A Storage',
                'remarks' => 'Missing one unit',
            ])
            ->assertRedirect();

        $claim = Claim::query()->where('office_id', $this->officeA->id)->firstOrFail();

        $this->assertSame('OPEN', $claim->status);
        $this->assertSame($this->officeUserA->id, $claim->claimed_by);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => Claim::class,
            'reference_id' => $claim->claims_id,
            'new_status' => 'OPEN',
        ]);

        $this->actingAs($this->officeUserA)
            ->post(route('office.claims.store'), [
                'distribution_record_id' => $recordB->distribution_record_id,
                'office_id' => $this->officeB->id,
                'claim_type' => 'DAMAGED_ITEM',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office B Storage',
            ])
            ->assertSessionHasErrors('office_id');

        $this->actingAs($this->gsoOfficer)
            ->put(route('inventory.claims.update-status', $claim->claims_id), [
                'status' => 'IN_REVIEW',
                'remarks' => 'Investigating',
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('IN_REVIEW', $claim->status);

        $this->assertDatabaseHas('approval_history', [
            'reference_type' => Claim::class,
            'reference_id' => $claim->claims_id,
            'status' => 'IN_REVIEW',
        ]);
    }

    public function test_distribution_item_update_rejects_cross_record_item_binding(): void
    {
        $recordOne = $this->createReleasedDistributionRecordForOffice($this->officeA, 9008);
        $recordTwo = $this->createReleasedDistributionRecordForOffice($this->officeA, 9009);

        $itemTwo = DistributionItem::query()->where('distribution_record_id', $recordTwo->distribution_record_id)->firstOrFail();

        $service = app(DistributionRecordService::class);

        $this->expectException(ValidationException::class);

        $service->updateDistributionItem($recordOne, $itemTwo, $this->gsoOfficer, [
            'quantity_released' => 1,
        ]);
    }

    public function test_distribution_item_update_keeps_inventory_summary_and_movements_consistent(): void
    {
        $context = $this->createVerifiedDeliveryContext($this->officeA, $this->officeUserA, $this->procurementOfficer, $this->gsoOfficer, 9010);

        Inventory::query()->create([
            'item_id' => 9010,
            'available_stock' => 10,
            'stock_on_hand' => 10,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 2,
            'last_updated' => now(),
        ]);

        $record = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-CONSIST-'.uniqid(),
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'delivery_id' => $context['delivery']->delivery_id,
            'office_id' => $this->officeA->id,
            'prepared_by' => $this->gsoOfficer->id,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.distribution-records.items.store', $record->distribution_record_id), [
                'item_id' => 9010,
                'item_name_snapshot' => 'Item 9010',
                'quantity_released' => 4,
                'unit' => 'PCS',
            ])
            ->assertRedirect();

        $distributionItem = DistributionItem::query()
            ->where('distribution_record_id', $record->distribution_record_id)
            ->firstOrFail();

        $this->actingAs($this->gsoOfficer)
            ->put(route('inventory.distribution-records.items.update', [
                'distribution_record' => $record->distribution_record_id,
                'distribution_item' => $distributionItem->distribution_item_id,
            ]), [
                'quantity_released' => 2,
            ])
            ->assertRedirect();

        $inventory = Inventory::query()->where('item_id', 9010)->firstOrFail();
        $this->assertSame('8.00', (string) $inventory->available_stock);
        $this->assertSame('8.00', (string) $inventory->stock_on_hand);
        $this->assertSame('2.00', (string) $inventory->released_quantity);

        $netMovement = InventoryMovement::query()
            ->whereHas('inventory', fn ($query) => $query->where('item_id', 9010))
            ->sum('quantity');
        $this->assertSame(6.0, (float) $netMovement);

        $this->assertSame(1, InventoryMovement::query()
            ->whereHas('inventory', fn ($query) => $query->where('item_id', 9010))
            ->where('movement_type', 'RELEASE')
            ->count());

        $this->assertSame(1, InventoryMovement::query()
            ->whereHas('inventory', fn ($query) => $query->where('item_id', 9010))
            ->where('movement_type', 'RELEASE_REVERSAL')
            ->count());
    }

    public function test_procurement_officer_is_denied_inventory_distribution_and_claim_routes(): void
    {
        $this->actingAs($this->procurementOfficer)
            ->get(route('inventory.inventory.index'))
            ->assertForbidden();

        $this->actingAs($this->procurementOfficer)
            ->get(route('inventory.distribution-records.index'))
            ->assertForbidden();

        $this->actingAs($this->procurementOfficer)
            ->get(route('inventory.claims.index'))
            ->assertForbidden();
    }

    public function test_claim_creation_rejects_distribution_and_office_mismatch_even_for_gso(): void
    {
        $recordA = $this->createReleasedDistributionRecordForOffice($this->officeA, 9011);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.claims.store'), [
                'distribution_record_id' => $recordA->distribution_record_id,
                'office_id' => $this->officeB->id,
                'claim_type' => 'DAMAGED_ITEM',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Mismatch Test',
            ])
            ->assertSessionHasErrors('office_id');
    }

    public function test_gso_queue_procurement_creates_purchase_request_and_items_for_shortage(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9012, 10, 2);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $context['supplyRequest']->supply_request_id))
            ->assertRedirect(route('inventory.office-supply-requests.index'));

        $purchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $context['supplyRequest']->supply_request_id)
            ->firstOrFail();

        $this->assertSame('OPEN', $purchaseRequest->status);

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'item_id' => $context['item']->item_id,
            'quantity_requested' => 10,
        ]);
    }

    public function test_gso_queue_procurement_rejects_duplicate_queueing(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9013, 8, 1);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $context['supplyRequest']->supply_request_id))
            ->assertRedirect(route('inventory.office-supply-requests.index'));

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $context['supplyRequest']->supply_request_id))
            ->assertSessionHasErrors('supply_request_id');

        $this->assertSame(
            1,
            PurchaseRequest::query()->where('supply_request_id', $context['supplyRequest']->supply_request_id)->count()
        );
    }

    public function test_gso_queue_procurement_rejects_stock_ready_requests(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9014, 5, 10);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $context['supplyRequest']->supply_request_id))
            ->assertSessionHasErrors('supply_request_id');

        $this->assertDatabaseMissing('purchase_requests', [
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
        ]);
    }

    public function test_process_distribution_routes_to_supply_distribution_with_request_context(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9015, 4, 10);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.process-distribution', $context['supplyRequest']->supply_request_id))
            ->assertRedirect(route('inventory.distribution-records.create', [
                'supply_request_id' => $context['supplyRequest']->supply_request_id,
            ]));
    }

    public function test_distribution_create_lists_stock_ready_inventory_backed_request_without_delivery_context(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9016, 4, 10);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.distribution-records.create', [
                'supply_request_id' => $context['supplyRequest']->supply_request_id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('focusSupplyRequestId', $context['supplyRequest']->supply_request_id)
                ->where('focusReleasableReason', null)
                ->where('eligibleRows.0.supply_request_id', $context['supplyRequest']->supply_request_id)
                ->where('eligibleRows.0.release_basis', 'AVAILABLE_INVENTORY')
                ->where('eligibleRows.0.release_basis_label', 'From Available Inventory')
                ->where('eligibleRows.0.delivery_id', null)
            );
    }

    public function test_distribution_create_shows_procurement_backed_reason_when_verified_delivery_is_missing(): void
    {
        $context = $this->createVerifiedDeliveryContext($this->officeA, $this->officeUserA, $this->procurementOfficer, $this->gsoOfficer, 9017);
        $context['delivery']->forceFill(['receipt_status' => 'PENDING'])->save();

        $focusId = (int) $context['supplyRequest']->supply_request_id;

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.distribution-records.create', [
                'supply_request_id' => $focusId,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('focusSupplyRequestId', $focusId)
                ->where('focusReleasableReason', 'This request is procurement-backed and awaits verified delivery before distribution release.')
                ->where('eligibleRows', fn ($rows): bool => collect($rows)->firstWhere('supply_request_id', $focusId) === null)
            );
    }

    public function test_gso_can_create_distribution_record_for_stock_ready_request_without_delivery_context(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9018, 2, 10);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.distribution-records.store'), [
                'supply_request_id' => $context['supplyRequest']->supply_request_id,
                'office_id' => $this->officeA->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('distribution_records', [
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'office_id' => $this->officeA->id,
            'delivery_id' => null,
            'status' => 'DRAFT',
        ]);
    }

    public function test_procurement_requests_page_only_lists_active_low_stock_deficit_items(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Signal Category '.uniqid(),
            'standard_delivery_days' => 7,
            'description' => 'Signal checks',
            'status' => 'ACTIVE',
        ]);

        $atThresholdItem = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'At Threshold Item',
            'description' => 'At threshold',
            'default_unit' => 'PCS',
            'status' => 'ACTIVE',
        ]);

        $deficitItem = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Deficit Item',
            'description' => 'Below threshold',
            'default_unit' => 'PCS',
            'status' => 'ACTIVE',
        ]);

        Inventory::query()->create([
            'item_id' => $atThresholdItem->item_id,
            'available_stock' => 5,
            'stock_on_hand' => 5,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 5,
            'last_updated' => now(),
        ]);

        Inventory::query()->create([
            'item_id' => $deficitItem->item_id,
            'available_stock' => 2,
            'stock_on_hand' => 2,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 5,
            'last_updated' => now(),
        ]);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.procurement-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lowStockItems.0.item_name', 'Deficit Item')
                ->where('lowStockItems.0.deficit', 3)
                ->where('atReorderItems.0.item_name', 'At Threshold Item')
            );
    }

    public function test_low_stock_signal_initiation_creates_purchase_request_and_redirects_to_procurement_requests(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9020, 10, 1);

        $inventoryId = (int) Inventory::query()
            ->where('item_id', (int) $context['item']->item_id)
            ->value('inventory_id');

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.procurement-requests.initiate-low-stock', $inventoryId))
            ->assertRedirect(route('inventory.procurement-requests.index', [
                'focus_inventory_id' => $inventoryId,
            ]));

        $created = PurchaseRequest::query()->latest('purchase_request_id')->firstOrFail();

        $this->assertSame('OPEN', (string) $created->status);
        $this->assertStringContainsString('LOW_STOCK_SIGNAL:inventory:'.$inventoryId, (string) $created->remarks);

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => (int) $created->purchase_request_id,
            'item_id' => (int) $context['item']->item_id,
        ]);
    }

    public function test_low_stock_signal_without_eligible_shortage_request_creates_purchase_request_directly(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'No Shortage Candidate '.uniqid(),
            'standard_delivery_days' => 7,
            'description' => 'Low stock without shortage-request candidate',
            'status' => 'ACTIVE',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Low Stock With No Candidate',
            'description' => 'No candidate test item',
            'default_unit' => 'PCS',
            'status' => 'ACTIVE',
        ]);

        $inventory = Inventory::query()->create([
            'item_id' => $item->item_id,
            'available_stock' => 1,
            'stock_on_hand' => 1,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 4,
            'last_updated' => now(),
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.procurement-requests.initiate-low-stock', (int) $inventory->inventory_id))
            ->assertRedirect(route('inventory.procurement-requests.index', [
                'focus_inventory_id' => (int) $inventory->inventory_id,
            ]));

        $created = PurchaseRequest::query()->latest('purchase_request_id')->firstOrFail();

        $this->assertSame('OPEN', (string) $created->status);
        $this->assertSame((int) $this->gsoOfficer->id, (int) $created->requested_by);
        $this->assertStringContainsString('LOW_STOCK_SIGNAL:inventory:'.$inventory->inventory_id, (string) $created->remarks);

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => (int) $created->purchase_request_id,
            'item_id' => (int) $item->item_id,
            'quantity_requested' => 3,
        ]);
    }

    public function test_inventory_office_supply_request_show_exposes_real_items_without_hardcoded_data(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9021, 9, 4);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.office-supply-requests.show', $context['supplyRequest']->supply_request_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('request.request_code', $context['supplyRequest']->request_code)
                ->where('request.item_count', 1)
                ->where('request.items.0.item_name', $context['item']->item_name)
                ->where('request.items.0.quantity_requested', 9)
                ->where('request.items.0.available_stock', 4)
                ->where('request.items.0.shortage', 5)
            );
    }

    public function test_stock_ready_request_without_distribution_record_is_marked_ready_for_distribution(): void
    {
        $context = $this->createSubmittedSupplyRequestForQueue($this->officeA, $this->officeUserA, 9022, 2, 10);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.office-supply-requests.index', [
                'q' => $context['supplyRequest']->request_code,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.data.0.request_code', $context['supplyRequest']->request_code)
                ->where('requests.data.0.stock_assessment', 'STOCK_READY')
                ->where('requests.data.0.workflow_bucket', 'READY_FOR_DISTRIBUTION')
                ->where('requests.data.0.has_distribution_candidate', true)
            );
    }

    public function test_gso_origin_supply_request_is_excluded_from_inventory_office_supply_request_listing(): void
    {
        $gsoOffice = $this->createOffice('General Services Office', 'GSO');
        $gsoOfficeUser = $this->createUser(UserRole::OFFICE_USER, $gsoOffice);
        $context = $this->createSubmittedSupplyRequestForQueue($gsoOffice, $gsoOfficeUser, 9023, 2, 10);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.office-supply-requests.index', [
                'q' => $context['supplyRequest']->request_code,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.total', 0)
            );
    }

    public function test_gso_origin_supply_request_is_not_eligible_in_distribution_create_context(): void
    {
        $gsoOffice = $this->createOffice('General Services Office', 'GSO');
        $gsoOfficeUser = $this->createUser(UserRole::OFFICE_USER, $gsoOffice);
        $context = $this->createSubmittedSupplyRequestForQueue($gsoOffice, $gsoOfficeUser, 9024, 2, 10);

        $focusId = (int) $context['supplyRequest']->supply_request_id;

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.distribution-records.create', [
                'supply_request_id' => $focusId,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('focusSupplyRequestId', $focusId)
                ->where('focusReleasableReason', 'GSO-origin supply requests are internal logistics records and are not eligible for office distribution release.')
                ->where('eligibleRows', fn ($rows): bool => collect($rows)->firstWhere('supply_request_id', $focusId) === null)
            );
    }

    private function createReleasedDistributionRecordForOffice(Office $office, int $itemId): DistributionRecord
    {
        $officeUser = ((int) $office->id === (int) $this->officeA->id) ? $this->officeUserA : $this->officeUserB;

        $context = $this->createVerifiedDeliveryContext($office, $officeUser, $this->procurementOfficer, $this->gsoOfficer, $itemId);

        Inventory::query()->create([
            'item_id' => $itemId,
            'available_stock' => 10,
            'stock_on_hand' => 10,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 2,
            'last_updated' => now(),
        ]);

        $record = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-AUTO-'.uniqid(),
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'delivery_id' => $context['delivery']->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $this->gsoOfficer->id,
            'status' => 'RELEASED',
            'release_date' => now()->toDateString(),
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $record->distribution_record_id,
            'item_id' => $itemId,
            'received_by' => null,
            'item_name_snapshot' => 'Item '.$itemId,
            'quantity_released' => 1,
            'unit' => 'PCS',
            'remarks' => null,
        ]);

        return $record;
    }

    /**
     * @return array{item: Item, supplyRequest: SupplyRequest}
     */
    private function createSubmittedSupplyRequestForQueue(
        Office $office,
        User $officeUser,
        int $itemNumber,
        float $quantityRequested,
        float $availableStock,
    ): array {
        $category = ItemCategory::query()->create([
            'category_name' => 'Queue Category '.uniqid(),
            'standard_delivery_days' => 7,
            'description' => 'Queue test category',
            'status' => 'ACTIVE',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Queue Item '.$itemNumber,
            'description' => 'Queue test item',
            'default_unit' => 'PCS',
            'status' => 'ACTIVE',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'available_stock' => $availableStock,
            'stock_on_hand' => $availableStock,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 2,
            'last_updated' => now(),
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 1000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => $quantityRequested,
            'unit_cost' => 10,
            'estimated_budget' => $quantityRequested * 10,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-QUEUE-'.uniqid(),
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Queue procurement flow test',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => $quantityRequested,
        ]);

        return [
            'item' => $item,
            'supplyRequest' => $supplyRequest,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createVerifiedDeliveryContext(
        Office $office,
        User $officeUser,
        User $procurementOfficer,
        User $gsoOfficer,
        int $itemId,
    ): array {
        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 1000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $itemId,
            'item_category_id' => 10,
            'quantity' => 5,
            'unit_cost' => 100,
            'estimated_budget' => 500,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-INV-'.uniqid(),
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Inventory foundation test',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $supplyRequestItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $itemId,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Item '.$itemId,
            'quantity_requested' => 5,
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementOfficer->id,
            'status' => 'PROCESSING',
            'remarks' => 'Inventory foundation PR',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier '.uniqid(),
            'contact_person' => 'Contact',
            'email' => uniqid('supplier', true).'@test.local',
            'phone' => '09123456789',
            'address' => 'Address',
            'status' => 'ACTIVE',
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementOfficer->id,
            'po_number' => 'PO-INV-'.uniqid(),
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 500,
            'terms_notes' => 'Net 30',
            'remarks' => null,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supply_request_item_id' => $supplyRequestItem->supply_request_item_id,
            'item_id' => $itemId,
            'item_name_snapshot' => 'Item '.$itemId,
            'specification' => 'Spec',
            'quantity_ordered' => 5,
            'unit' => 'PCS',
            'unit_price' => 100,
            'total_price' => 500,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-INV-'.uniqid(),
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->addDay()->toDateString(),
            'received_by' => $gsoOfficer->id,
            'receipt_status' => 'VERIFIED',
            'remarks' => null,
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $purchaseOrderItem->purchase_order_item_id,
            'expected_quantity' => 5,
            'received_quantity' => 5,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => null,
        ]);

        return [
            'supplyRequest' => $supplyRequest,
            'delivery' => $delivery,
        ];
    }

    private function createOffice(string $name, string $code): Office
    {
        return Office::query()->create([
            'name' => $name,
            'code' => $code,
            'description' => $name.' Description',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for inventory distribution foundation tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
