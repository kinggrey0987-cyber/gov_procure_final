<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyRequestDraftCategoryPurityTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;
    private User $officeUser;
    private ItemCategory $officeSuppliesCategory;
    private ItemCategory $itCategory;
    private Ppmp $ppmp;
    private PpmpItem $ppmpItemPaper;
    private PpmpItem $ppmpItemLaptop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Municipal Planning and Development Office',
            'code' => 'MPDO',
            'status' => 'ACTIVE',
        ]);

        $role = Role::query()->firstOrCreate(
            ['code' => UserRole::OFFICE_USER->value],
            [
                'name' => 'Office User',
                'description' => 'Requisition Officer',
                'is_active' => true,
            ]
        );

        $this->officeUser = User::query()->create([
            'name' => 'Officer Requisitioner',
            'email' => 'requisitioner@example.gov.ph',
            'emp_id' => 'EMP-TEST-001',
            'password' => bcrypt('Secret123!'),
            'role_id' => $role->id,
            'office_id' => $this->office->id,
            'status' => 'ACTIVE',
        ]);

        $this->officeSuppliesCategory = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'category_code' => 'OFF-SUP',
            'status' => 'ACTIVE',
        ]);

        $this->itCategory = ItemCategory::query()->create([
            'category_name' => 'IT Equipment and Software',
            'category_code' => 'IT-EQP',
            'status' => 'ACTIVE',
        ]);

        $currentYear = (int) now()->format('Y');

        $this->ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'fiscal_year' => $currentYear,
            'status' => 'APPROVED',
            'total_estimated_budget' => 200000,
            'date_prepared' => now()->toDateString(),
        ]);

        $itemPaper = Item::query()->create([
            'item_name' => 'Bond Paper A4',
            'item_category_id' => $this->officeSuppliesCategory->item_category_id,
            'default_unit' => 'ream',
            'status' => 'active',
        ]);

        $itemLaptop = Item::query()->create([
            'item_name' => 'Laptop Core i7',
            'item_category_id' => $this->itCategory->item_category_id,
            'default_unit' => 'unit',
            'status' => 'active',
        ]);

        $this->ppmpItemPaper = PpmpItem::query()->create([
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_id' => $itemPaper->item_id,
            'item_category_id' => $this->officeSuppliesCategory->item_category_id,
            'quantity' => 100,
            'unit_cost' => 250,
            'estimated_budget' => 25000,
            'procurement_schedule' => 'Q1',
            'remarks' => '70 GSM',
        ]);

        $this->ppmpItemLaptop = PpmpItem::query()->create([
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_id' => $itemLaptop->item_id,
            'item_category_id' => $this->itCategory->item_category_id,
            'quantity' => 5,
            'unit_cost' => 45000,
            'estimated_budget' => 225000,
            'procurement_schedule' => 'Q1',
            'remarks' => '16GB RAM',
        ]);
    }

    public function test_changing_category_on_draft_supply_request_is_rejected_if_existing_items_belong_to_another_category(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-001',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_category_id' => $this->officeSuppliesCategory->item_category_id,
            'request_category' => $this->officeSuppliesCategory->category_name,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'purpose' => 'Printing documents',
            'priority_level' => 'NORMAL',
            'status' => 'DRAFT',
        ]);

        // Add Bond Paper (Office Supplies) to this request
        $item = SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $this->ppmpItemPaper->item_id,
            'ppmp_item_id' => $this->ppmpItemPaper->ppmp_item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 10,
        ]);

        // Attempting to change category to IT Equipment while Bond Paper is attached MUST fail validation
        $response = $this->actingAs($this->officeUser)
            ->put(route('office.supply-requests.update', $supplyRequest->supply_request_id), [
                'ppmp_id' => $this->ppmp->ppmp_id,
                'item_category_id' => $this->itCategory->item_category_id,
                'request_category' => $this->itCategory->category_name,
                'needed_by_date' => now()->addDays(5)->toDateString(),
                'purpose' => 'Printing documents',
                'priority_level' => 'NORMAL',
            ]);

        $response->assertSessionHasErrors('item_category_id');

        $supplyRequest->refresh();
        $this->assertSame($this->officeSuppliesCategory->item_category_id, $supplyRequest->item_category_id);

        // Remove the Bond Paper line
        $this->actingAs($this->officeUser)
            ->delete(route('office.supply-requests.items.destroy', [$supplyRequest->supply_request_id, $item->supply_request_item_id]))
            ->assertRedirect();

        // Now changing category to IT Equipment MUST succeed
        $responseSuccess = $this->actingAs($this->officeUser)
            ->put(route('office.supply-requests.update', $supplyRequest->supply_request_id), [
                'ppmp_id' => $this->ppmp->ppmp_id,
                'item_category_id' => $this->itCategory->item_category_id,
                'request_category' => $this->itCategory->category_name,
                'needed_by_date' => now()->addDays(5)->toDateString(),
                'purpose' => 'Office computers',
                'priority_level' => 'NORMAL',
            ]);

        $responseSuccess->assertSessionHasNoErrors();
        $supplyRequest->refresh();
        $this->assertSame($this->itCategory->item_category_id, $supplyRequest->item_category_id);
    }

    public function test_adding_item_with_mismatched_category_to_draft_supply_request_is_rejected(): void
    {
        // Supply Request is for Office Supplies
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-002',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_category_id' => $this->officeSuppliesCategory->item_category_id,
            'request_category' => $this->officeSuppliesCategory->category_name,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'purpose' => 'Office supplies need',
            'priority_level' => 'NORMAL',
            'status' => 'DRAFT',
        ]);

        // Attempting to add Laptop (IT Equipment) to an Office Supplies request MUST fail
        $responseReject = $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.items.store', $supplyRequest->supply_request_id), [
                'item_id' => $this->ppmpItemLaptop->item_id,
                'ppmp_item_id' => $this->ppmpItemLaptop->ppmp_item_id,
                'item_name_snapshot' => 'Laptop Core i7',
                'quantity_requested' => 1,
            ]);

        $responseReject->assertSessionHasErrors('ppmp_item_id');
        $this->assertSame(0, $supplyRequest->items()->count());

        // Adding Bond Paper (Office Supplies) MUST succeed
        $responseSuccess = $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.items.store', $supplyRequest->supply_request_id), [
                'item_id' => $this->ppmpItemPaper->item_id,
                'ppmp_item_id' => $this->ppmpItemPaper->ppmp_item_id,
                'item_name_snapshot' => 'Bond Paper A4',
                'quantity_requested' => 5,
            ]);

        $responseSuccess->assertSessionHasNoErrors();
        $this->assertSame(1, $supplyRequest->items()->count());
    }

    public function test_submitting_draft_supply_request_rejects_category_mismatched_items(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-003',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_category_id' => $this->officeSuppliesCategory->item_category_id,
            'request_category' => $this->officeSuppliesCategory->category_name,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'purpose' => 'Office supplies',
            'priority_level' => 'NORMAL',
            'status' => 'DRAFT',
        ]);

        // Force an IT item into an Office Supplies request
        SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $this->ppmpItemLaptop->item_id,
            'ppmp_item_id' => $this->ppmpItemLaptop->ppmp_item_id,
            'item_name_snapshot' => 'Laptop Core i7',
            'quantity_requested' => 1,
        ]);

        // Submitting this request MUST fail validation
        $response = $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.submit', $supplyRequest->supply_request_id), [
                'review_notes' => 'Please approve',
            ]);

        $response->assertSessionHasErrors('items');
        $supplyRequest->refresh();
        $this->assertSame('DRAFT', $supplyRequest->status);
    }

    public function test_legacy_supply_request_without_category_id_still_allows_updates_and_items(): void
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-004',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_category_id' => null,
            'request_category' => 'GENERAL',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'purpose' => 'Legacy general request',
            'priority_level' => 'NORMAL',
            'status' => 'DRAFT',
        ]);

        // Adding items to a general request without category_id succeeds without restriction
        $response = $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.items.store', $supplyRequest->supply_request_id), [
                'item_id' => $this->ppmpItemPaper->item_id,
                'ppmp_item_id' => $this->ppmpItemPaper->ppmp_item_id,
                'item_name_snapshot' => 'Bond Paper A4',
                'quantity_requested' => 2,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $supplyRequest->items()->count());

        // Updating details also succeeds
        $updateResponse = $this->actingAs($this->officeUser)
            ->put(route('office.supply-requests.update', $supplyRequest->supply_request_id), [
                'ppmp_id' => $this->ppmp->ppmp_id,
                'request_category' => 'GENERAL',
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'purpose' => 'Updated legacy purpose',
                'priority_level' => 'NORMAL',
            ]);

        $updateResponse->assertSessionHasNoErrors();
    }
}
