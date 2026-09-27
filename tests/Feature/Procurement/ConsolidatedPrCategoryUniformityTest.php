<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedPrCategoryUniformityTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;
    private User $gsoOfficer;
    private User $officeUser;
    private ItemCategory $officeSuppliesCategory;
    private ItemCategory $itEquipmentCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Municipal Planning and Development Office',
            'code' => 'MPDO',
            'status' => 'ACTIVE',
        ]);

        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);

        $this->officeSuppliesCategory = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'category_code' => 'OFF-SUP',
            'status' => 'ACTIVE',
        ]);

        $this->itEquipmentCategory = ItemCategory::query()->create([
            'category_name' => 'IT Equipment and Software',
            'category_code' => 'IT-EQP',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_consolidation_rejects_requests_from_different_commodity_categories(): void
    {
        $itemPaper = $this->createItemWithInventory('Bond Paper A4', 0, $this->officeSuppliesCategory);
        $itemLaptop = $this->createItemWithInventory('Laptop Core i7', 0, $this->itEquipmentCategory);

        $srOfficeSupplies = $this->createSupplyRequest($this->officeSuppliesCategory, [
            ['item' => $itemPaper, 'quantity' => 10],
        ]);

        $srItEquipment = $this->createSupplyRequest($this->itEquipmentCategory, [
            ['item' => $itemLaptop, 'quantity' => 2],
        ]);

        // Attempting to consolidate requests of different categories MUST fail validation
        $response = $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.consolidated-queue'), [
                'office_id' => $this->office->id,
                'supply_request_ids' => [
                    $srOfficeSupplies->supply_request_id,
                    $srItEquipment->supply_request_id,
                ],
            ]);

        $response->assertSessionHasErrors('supply_request_ids');
        $this->assertDatabaseMissing('purchase_requests', [
            'office_id' => $this->office->id,
            'remarks' => sprintf('Consolidated PR for %s', $this->office->name),
        ]);
    }

    public function test_consolidation_succeeds_when_all_requests_share_the_same_category(): void
    {
        $itemPaper = $this->createItemWithInventory('Bond Paper A4', 0, $this->officeSuppliesCategory);
        $itemPen = $this->createItemWithInventory('Ballpoint Pen Black', 0, $this->officeSuppliesCategory);

        $sr1 = $this->createSupplyRequest($this->officeSuppliesCategory, [
            ['item' => $itemPaper, 'quantity' => 10],
        ]);

        $sr2 = $this->createSupplyRequest($this->officeSuppliesCategory, [
            ['item' => $itemPen, 'quantity' => 50],
        ]);

        $response = $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.consolidated-queue'), [
                'office_id' => $this->office->id,
                'supply_request_ids' => [
                    $sr1->supply_request_id,
                    $sr2->supply_request_id,
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pr = PurchaseRequest::query()
            ->where('remarks', 'like', '%Consolidated PR for%')
            ->latest('purchase_request_id')
            ->firstOrFail();

        $this->assertStringContainsString('Office Supplies', (string) $pr->remarks);
        $this->assertSame(2, $pr->items()->count());
    }

    public function test_office_supply_requests_index_groups_shortages_by_office_and_category(): void
    {
        $itemPaper = $this->createItemWithInventory('Bond Paper A4', 0, $this->officeSuppliesCategory);
        $itemPen = $this->createItemWithInventory('Ballpoint Pen Black', 0, $this->officeSuppliesCategory);
        $itemLaptop = $this->createItemWithInventory('Laptop Core i7', 0, $this->itEquipmentCategory);
        $itemMouse = $this->createItemWithInventory('Optical Mouse USB', 0, $this->itEquipmentCategory);

        // 2 requests in Office Supplies
        $this->createSupplyRequest($this->officeSuppliesCategory, [['item' => $itemPaper, 'quantity' => 5]]);
        $this->createSupplyRequest($this->officeSuppliesCategory, [['item' => $itemPen, 'quantity' => 20]]);

        // 2 requests in IT Equipment
        $this->createSupplyRequest($this->itEquipmentCategory, [['item' => $itemLaptop, 'quantity' => 1]]);
        $this->createSupplyRequest($this->itEquipmentCategory, [['item' => $itemMouse, 'quantity' => 5]]);

        $response = $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.office-supply-requests.index'));

        $response->assertOk();
        $groups = $response->viewData('page')['props']['officeShortageGroups'];

        // Should have 2 distinct groups for this office: one for Office Supplies (count: 2) and one for IT Equipment (count: 2)
        $this->assertCount(2, $groups);

        $categoryNames = collect($groups)->pluck('category_name')->all();
        $this->assertContains('Office Supplies', $categoryNames);
        $this->assertContains('IT Equipment and Software', $categoryNames);

        foreach ($groups as $group) {
            $this->assertSame(2, $group['count']);
            $this->assertSame($this->office->id, $group['id']);
        }
    }

    /**
     * @param array<int, array{item: Item, quantity: float}> $items
     */
    private function createSupplyRequest(ItemCategory $category, array $items): SupplyRequest
    {
        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'total_estimated_budget' => 5000,
            'date_prepared' => now()->toDateString(),
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-'.uniqid(),
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'item_category_id' => $category->item_category_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Test supply request',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
        ]);

        foreach ($items as $entry) {
            $item = $entry['item'];
            $qty = $entry['quantity'];

            $ppmpItem = PpmpItem::query()->create([
                'ppmp_id' => $ppmp->ppmp_id,
                'item_id' => $item->item_id,
                'item_category_id' => $category->item_category_id,
                'quantity' => $qty,
                'unit_cost' => 50,
                'estimated_budget' => $qty * 50,
                'procurement_schedule' => 'Q1',
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $supplyRequest->supply_request_id,
                'item_id' => $item->item_id,
                'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => $item->item_name,
                'quantity_requested' => $qty,
            ]);
        }

        return $supplyRequest;
    }

    private function createItemWithInventory(string $name, float $availableStock, ItemCategory $category): Item
    {
        $item = Item::query()->create([
            'item_name' => $name,
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'piece',
            'status' => 'active',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'available_stock' => $availableStock,
            'stock_on_hand' => $availableStock,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 5,
            'last_updated' => now(),
        ]);

        return $item;
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for testing',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
