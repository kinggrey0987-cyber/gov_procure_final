<?php

namespace Tests\Feature\Inventory;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FollowUpDeficitCapAndDeliveryReceiptValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $gsoUser;
    private Office $gsoOffice;
    private Office $targetOffice;
    private Item $item;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $gsoRole = Role::firstOrCreate(['name' => 'GSO Officer'], ['code' => 'GSO_OFFICER']);
        $this->gsoOffice = Office::firstOrCreate(['name' => 'General Services Office'], ['code' => 'GSO']);
        $this->targetOffice = Office::firstOrCreate(['name' => 'MPDO Office'], ['code' => 'MPDO']);

        $this->gsoUser = User::factory()->create([
            'role_id' => $gsoRole->id,
            'office_id' => $this->gsoOffice->id,
        ]);

        $this->supplier = Supplier::create([
            'supplier_name' => 'Grand Alliance Commercial Corp.',
            'contact_person' => 'Juan Dela Cruz',
            'email' => 'supplier@example.com',
            'phone' => '09123456789',
        ]);

        $category = ItemCategory::firstOrCreate(['category_name' => 'Office Supplies'], ['status' => 'ACTIVE']);
        $this->item = Item::create([
            'item_code' => 'ITEM-BOND-A4',
            'item_name' => 'Bond Paper A4',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        Inventory::create([
            'item_id' => $this->item->item_id,
            'stock_on_hand' => 24,
            'available_stock' => 24,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_delivery_receipt_and_sales_invoice_must_contain_numbers(): void
    {
        $sr = SupplyRequest::create([
            'request_code' => 'SR-TEST-0001',
            'office_id' => $this->targetOffice->id,
            'requested_by' => $this->gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Test Requisition',
            'status' => 'FOR_PROCUREMENT',
        ]);

        $srItem = SupplyRequestItem::create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
        ]);

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-TEST-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->targetOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-0001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->gsoUser->id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'placed_at' => now()->subDay(),
            'total_amount' => 1000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $srItem->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 10,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        $delivery = Delivery::create([
            'delivery_code' => 'DEL-TEST-0001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'receipt_status' => 'PENDING',
        ]);

        $deliveryItem = DeliveryItem::create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 0,
            'damaged_quantity' => 0,
        ]);

        // Submit with pure letters (no numbers) like "yt"
        $response = $this->actingAs($this->gsoUser)
            ->put(route('inventory.deliveries.update', $delivery->delivery_id), [
                'actual_delivery_date' => now()->toDateString(),
                'delivery_receipt_no' => 'yt',
                'sales_invoice_no' => 'yt',
                'delivery_receipt_file' => UploadedFile::fake()->create('dr.pdf', 100, 'application/pdf'),
                'sales_invoice_file' => UploadedFile::fake()->create('si.pdf', 100, 'application/pdf'),
                'items' => [
                    [
                        'delivery_item_id' => $deliveryItem->delivery_item_id,
                        'received_quantity' => 10,
                        'damaged_quantity' => 0,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['delivery_receipt_no', 'sales_invoice_no']);

        // Submit with valid numbers in DR and SI (e.g., DR-2026-0814 and SI-44102)
        $validResponse = $this->actingAs($this->gsoUser)
            ->put(route('inventory.deliveries.update', $delivery->delivery_id), [
                'actual_delivery_date' => now()->toDateString(),
                'delivery_receipt_no' => 'DR-2026-0814',
                'sales_invoice_no' => 'SI-44102',
                'delivery_receipt_file' => UploadedFile::fake()->create('dr.pdf', 100, 'application/pdf'),
                'sales_invoice_file' => UploadedFile::fake()->create('si.pdf', 100, 'application/pdf'),
                'items' => [
                    [
                        'delivery_item_id' => $deliveryItem->delivery_item_id,
                        'received_quantity' => 10,
                        'damaged_quantity' => 0,
                    ],
                ],
            ]);

        $validResponse->assertSessionHasNoErrors();
    }

    public function test_follow_up_distribution_caps_release_at_parent_deficit_and_retains_surplus_in_gso_stock(): void
    {
        $distService = app(DistributionRecordService::class);

        // 1. Parent Supply Request: 100 boxes requested
        $parentSr = SupplyRequest::create([
            'request_code' => 'SR-MPDO-PARENT-001',
            'office_id' => $this->targetOffice->id,
            'requested_by' => $this->gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Initial Request',
            'status' => 'SUBMITTED',
        ]);

        SupplyRequestItem::create([
            'supply_request_id' => $parentSr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        // 2. Initial Distribution: release the 24 available stock
        $initialRecord = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $parentSr->supply_request_id,
            'office_id' => $this->targetOffice->id,
        ]);

        $this->assertEquals(24.0, (float) $initialRecord->items->sum('quantity_released'));

        // Inventory is now 0
        $inv = Inventory::where('item_id', $this->item->item_id)->first();
        $this->assertEquals(0.0, (float) $inv->available_stock);

        // 3. Child Follow-Up Supply Request: Jeron creates SR/PR for 80 units
        $childSr = SupplyRequest::create([
            'request_code' => 'SR-MPDO-CHILD-002',
            'office_id' => $this->targetOffice->id,
            'requested_by' => $this->gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Follow-up Request',
            'status' => 'SUBMITTED',
        ]);

        $childItem = SupplyRequestItem::create([
            'supply_request_id' => $childSr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 80,
        ]);

        $followUpPr = PurchaseRequest::create([
            'pr_number' => 'PR-FOLLOWUP-001',
            'supply_request_id' => $childSr->supply_request_id,
            'source_supply_request_id' => $parentSr->supply_request_id,
            'source_type' => 'REQUISITION_FOLLOW_UP',
            'office_id' => $this->targetOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'item_id' => $this->item->item_id,
            'quantity_requested' => 80,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-FOLLOWUP-001',
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->gsoUser->id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'supply_request_id' => $childSr->supply_request_id,
            'status' => 'PLACED',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $childItem->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 80,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 8000,
        ]);

        // 4. Delivery of 80 units arrives and is verified into GSO inventory
        $delivery = Delivery::create([
            'delivery_code' => 'DEL-FOLLOWUP-001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 80,
            'received_quantity' => 80,
            'damaged_quantity' => 0,
        ]);

        // Inventory is credited with 80
        $inv->forceFill([
            'stock_on_hand' => 80,
            'available_stock' => 80,
        ])->save();

        // 5. Automatic distribution record is created for the child SR from the delivery
        $childRecord = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $childSr->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $this->targetOffice->id,
        ]);

        // CRITICAL ASSERTION: The child record MUST only release 76 units (100 parent requested - 24 initial released)!
        $this->assertEquals(76.0, (float) $childRecord->items->sum('quantity_released'));

        // CRITICAL ASSERTION: The remaining 4 units MUST stay in GSO warehouse inventory!
        $inv->refresh();
        $this->assertEquals(4.0, (float) $inv->stock_on_hand);
        $this->assertEquals(4.0, (float) $inv->available_stock);
    }
}
