<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Anomaly;
use App\Models\Delivery;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeTransactionalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_ppmp_create_update_submit_writes_status_history(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $office);

        $create = $this->actingAs($officeUser)->post(route('office.ppmp.store'), [
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'date_prepared' => now()->toDateString(),
            'remarks' => 'Initial',
        ]);
        $create->assertRedirect();

        $ppmp = Ppmp::query()->firstOrFail();

        $this->actingAs($officeUser)->post(route('office.ppmp.items.store', $ppmp->ppmp_id), [
            'item_id' => 100,
            'item_category_id' => 10,
            'quantity' => 2,
            'unit_cost' => 150,
            'procurement_schedule' => 'Q1',
            'remarks' => 'Item row',
        ])->assertRedirect();

        $this->actingAs($officeUser)->put(route('office.ppmp.update', $ppmp->ppmp_id), [
            'fiscal_year' => 2027,
            'fund_source' => 'GAA_CONTINUING',
            'date_prepared' => now()->toDateString(),
            'remarks' => 'Updated',
        ])->assertRedirect();

        $this->actingAs($officeUser)->post(route('office.ppmp.submit', $ppmp->ppmp_id), [
            'remarks' => 'Submitting',
        ])->assertRedirect();

        $ppmp->refresh();
        $this->assertSame('SUBMITTED', $ppmp->status);

        $history = StatusHistory::query()
            ->where('reference_type', Ppmp::class)
            ->where('reference_id', $ppmp->ppmp_id)
            ->pluck('new_status')
            ->all();

        $this->assertContains('DRAFT', $history);
        $this->assertContains('SUBMITTED', $history);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $executive->id,
            'notification_type' => 'EXEC_PPMP_PENDING_APPROVAL',
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'is_read' => false,
        ]);
    }

    public function test_ppmp_edit_page_loads_existing_item_fields_for_editing_context(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 400,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Edit context test',
        ]);

        PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 101,
            'item_category_id' => 11,
            'quantity' => 2,
            'unit_cost' => 200,
            'estimated_budget' => 400,
            'procurement_schedule' => 'Q1',
            'remarks' => 'Existing line',
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/Ppmp/Edit')
                ->where('ppmp.ppmp_id', $ppmp->ppmp_id)
                ->has('ppmp.items', 1)
                ->where('ppmp.items.0.quantity', '2.00')
                ->where('ppmp.items.0.unit_cost', '200.00')
                ->where('ppmp.items.0.estimated_budget', '400.00')
                ->where('ppmp.items.0.procurement_schedule', 'Q1')
            );
    }

    public function test_office_user_can_update_and_remove_ppmp_item_lines(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 150,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $item = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 102,
            'item_category_id' => 12,
            'quantity' => 3,
            'unit_cost' => 50,
            'estimated_budget' => 150,
            'procurement_schedule' => 'Q2',
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->put(route('office.ppmp.items.update', [$ppmp->ppmp_id, $item->ppmp_item_id]), [
                'item_id' => 102,
                'item_category_id' => 12,
                'quantity' => 4,
                'unit_cost' => 75,
                'procurement_schedule' => 'Q3',
                'remarks' => 'Updated row',
            ])
            ->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));

        $this->assertDatabaseHas('ppmp_items', [
            'ppmp_item_id' => $item->ppmp_item_id,
            'quantity' => 4,
            'unit_cost' => 75,
            'estimated_budget' => 300,
            'procurement_schedule' => 'Q3',
            'remarks' => 'Updated row',
        ]);

        $this->actingAs($officeUser)
            ->delete(route('office.ppmp.items.destroy', [$ppmp->ppmp_id, $item->ppmp_item_id]))
            ->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));

        $this->assertDatabaseMissing('ppmp_items', [
            'ppmp_item_id' => $item->ppmp_item_id,
        ]);
    }

    public function test_ppmp_item_add_from_edit_flow_redirects_back_to_edit_page(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Draft edit flow',
        ]);

        $response = $this->actingAs($officeUser)
            ->post(route('office.ppmp.items.store', $ppmp->ppmp_id), [
                'item_id' => 777,
                'item_category_id' => 77,
                'quantity' => 5,
                'unit_cost' => 20,
                'procurement_schedule' => 'Q2',
                'remarks' => 'Added from edit',
            ]);

        $response->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));
        $response->assertSessionHas('success', 'PPMP item added.');

        $this->assertDatabaseHas('ppmp_items', [
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 777,
            'item_category_id' => 77,
            'quantity' => 5,
            'unit_cost' => 20,
            'estimated_budget' => 100,
            'procurement_schedule' => 'Q2',
            'remarks' => 'Added from edit',
        ]);
    }

    public function test_ppmp_item_add_validation_errors_return_to_edit_page(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Draft validation flow',
        ]);

        $response = $this->actingAs($officeUser)
            ->from(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->post(route('office.ppmp.items.store', $ppmp->ppmp_id), [
                'item_id' => 0,
                'item_category_id' => 0,
                'quantity' => 0,
                'unit_cost' => -1,
                'procurement_schedule' => '',
            ]);

        $response->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));
        $response->assertSessionHasErrors(['quantity', 'unit_cost', 'procurement_schedule']);
    }

    public function test_ppmp_creation_rejects_zero_unit_cost(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $category = ItemCategory::query()->create([
            'category_name' => 'PPMP Validation Category',
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'PPMP Validation Item',
            'description' => 'Unit cost validation item',
            'default_unit' => 'piece',
            'status' => 'active',
        ]);

        $response = $this->actingAs($officeUser)
            ->from(route('office.ppmp.create'))
            ->post(route('office.ppmp.store'), [
                'fiscal_year' => 2026,
                'fund_source' => 'GAA_CURRENT',
                'date_prepared' => now()->toDateString(),
                'remarks' => 'Unit cost validation',
                'items' => [[
                    'item_id' => $item->item_id,
                    'item_category_id' => $category->item_category_id,
                    'quantity' => 10,
                    'unit_cost' => 0,
                    'procurement_schedule' => 'Q1',
                    'remarks' => 'Item row',
                ]],
            ]);

        $response->assertRedirect(route('office.ppmp.create'));
        $response->assertSessionHasErrors([
            'items.0.unit_cost' => 'Unit Cost must be greater than zero.',
        ]);
        $this->assertDatabaseCount('ppmp', 0);
    }

    public function test_ppmp_item_add_and_update_reject_zero_unit_cost(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 50,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Draft validation flow',
        ]);

        $item = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 102,
            'item_category_id' => 12,
            'quantity' => 1,
            'unit_cost' => 50,
            'estimated_budget' => 50,
            'procurement_schedule' => 'Q2',
            'remarks' => 'Existing row',
        ]);

        $this->actingAs($officeUser)
            ->from(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->post(route('office.ppmp.items.store', $ppmp->ppmp_id), [
                'item_id' => 777,
                'item_category_id' => 77,
                'quantity' => 5,
                'unit_cost' => 0,
                'procurement_schedule' => 'Q3',
                'remarks' => 'Added from edit',
            ])
            ->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->assertSessionHasErrors([
                'unit_cost' => 'Unit Cost must be greater than zero.',
            ]);

        $this->actingAs($officeUser)
            ->from(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->put(route('office.ppmp.items.update', [$ppmp->ppmp_id, $item->ppmp_item_id]), [
                'item_id' => 102,
                'item_category_id' => 12,
                'quantity' => 4,
                'unit_cost' => 0,
                'procurement_schedule' => 'Q3',
                'remarks' => 'Updated row',
            ])
            ->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->assertSessionHasErrors([
                'unit_cost' => 'Unit Cost must be greater than zero.',
            ]);

        $this->assertDatabaseHas('ppmp_items', [
            'ppmp_item_id' => $item->ppmp_item_id,
            'unit_cost' => 50,
            'estimated_budget' => 50,
        ]);
    }

    public function test_office_user_can_view_return_or_reject_reasons_in_ppmp_history(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Office submission',
        ]);

        $this->actingAs($executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => 'Revise the schedule and clarify item quantities.',
            ])
            ->assertRedirect(route('executive.ppmp-approvals.show', $ppmp->ppmp_id));

        $this->actingAs($officeUser)
            ->get(route('office.ppmp.show', $ppmp->ppmp_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/Ppmp/Show')
                ->has('statusHistory', 1)
                ->has('approvalHistory', 1)
                ->where('statusHistory.0.new_status', 'RETURNED_FOR_REVISION')
                ->where('statusHistory.0.remarks', 'Revise the schedule and clarify item quantities.')
                ->where('approvalHistory.0.status', 'RETURNED_FOR_REVISION')
                ->where('approvalHistory.0.remarks', 'Revise the schedule and clarify item quantities.')
            );
    }

    public function test_office_user_can_view_rejection_reason_in_ppmp_history(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Office submission',
        ]);

        $this->actingAs($executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'REJECTED',
                'remarks' => 'Rejected because supporting breakdowns were not provided.',
            ])
            ->assertRedirect(route('executive.ppmp-approvals.show', $ppmp->ppmp_id));

        $this->actingAs($officeUser)
            ->get(route('office.ppmp.show', $ppmp->ppmp_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/Ppmp/Show')
                ->where('statusHistory.0.new_status', 'REJECTED')
                ->where('statusHistory.0.remarks', 'Rejected because supporting breakdowns were not provided.')
                ->where('approvalHistory.0.status', 'REJECTED')
                ->where('approvalHistory.0.remarks', 'Rejected because supporting breakdowns were not provided.')
            );
    }

    public function test_ppmp_item_creation_accepts_all_valid_quarter_schedule_values(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Quarter schedule coverage',
        ]);

        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter) {
            $this->actingAs($officeUser)
                ->post(route('office.ppmp.items.store', $ppmp->ppmp_id), [
                    'item_id' => 800 + (int) substr($quarter, 1),
                    'item_category_id' => 80,
                    'quantity' => 2,
                    'unit_cost' => 10,
                    'procurement_schedule' => $quarter,
                    'remarks' => 'Quarter '.$quarter,
                ])
                ->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));

            $this->assertDatabaseHas('ppmp_items', [
                'ppmp_id' => $ppmp->ppmp_id,
                'procurement_schedule' => $quarter,
            ]);
        }
    }

    public function test_ppmp_item_creation_rejects_invalid_free_text_schedule(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'Invalid quarter test',
        ]);

        $response = $this->actingAs($officeUser)
            ->from(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->post(route('office.ppmp.items.store', $ppmp->ppmp_id), [
                'item_id' => 999,
                'item_category_id' => 99,
                'quantity' => 2,
                'unit_cost' => 10,
                'procurement_schedule' => 'First Quarter',
                'remarks' => null,
            ]);

        $response->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));
        $response->assertSessionHasErrors(['procurement_schedule']);
        $response->assertSessionHasErrors([
            'procurement_schedule' => 'Procurement Schedule must be one of: Q1, Q2, Q3, or Q4.',
        ]);
    }

    public function test_ppmp_item_update_rejects_invalid_free_text_schedule(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 150,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $item = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 102,
            'item_category_id' => 12,
            'quantity' => 3,
            'unit_cost' => 50,
            'estimated_budget' => 150,
            'procurement_schedule' => 'Q2',
            'remarks' => null,
        ]);

        $response = $this->actingAs($officeUser)
            ->from(route('office.ppmp.edit', $ppmp->ppmp_id))
            ->put(route('office.ppmp.items.update', [$ppmp->ppmp_id, $item->ppmp_item_id]), [
                'item_id' => 102,
                'item_category_id' => 12,
                'quantity' => 4,
                'unit_cost' => 75,
                'procurement_schedule' => 'Quarter 3',
                'remarks' => 'Invalid quarter update',
            ]);

        $response->assertRedirect(route('office.ppmp.edit', $ppmp->ppmp_id));
        $response->assertSessionHasErrors(['procurement_schedule']);
        $response->assertSessionHasErrors([
            'procurement_schedule' => 'Procurement Schedule must be one of: Q1, Q2, Q3, or Q4.',
        ]);
    }

    public function test_supply_request_create_update_submit_writes_status_history(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 300,
            'item_category_id' => 30,
            'quantity' => 50,
            'unit_cost' => 100,
            'estimated_budget' => 5000,
            'procurement_schedule' => 'Q2',
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Need paper',
            'priority_level' => 'MEDIUM',
            'review_notes' => 'Draft',
        ])->assertRedirect();

        $requestModel = SupplyRequest::query()->firstOrFail();

        $itemResponse = $this->actingAs($officeUser)->post(route('office.supply-requests.items.store', $requestModel->supply_request_id), [
            'item_id' => 300,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Office Item 300',
            'quantity_requested' => 10,
        ]);
        $itemResponse->assertRedirect();


        $this->actingAs($officeUser)->put(route('office.supply-requests.update', $requestModel->supply_request_id), [
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Need more paper',
            'priority_level' => 'HIGH',
            'review_notes' => 'Updated',
        ])->assertRedirect();

        $this->actingAs($officeUser)->post(route('office.supply-requests.submit', $requestModel->supply_request_id), [
            'review_notes' => 'Submit',
        ])->assertRedirect();

        $requestModel->refresh();
        $this->assertSame('SUBMITTED', $requestModel->status);

        $history = StatusHistory::query()
            ->where('reference_type', SupplyRequest::class)
            ->where('reference_id', $requestModel->supply_request_id)
            ->pluck('new_status')
            ->all();

        $this->assertContains('DRAFT', $history);
        $this->assertContains('SUBMITTED', $history);
    }

    public function test_supply_request_item_snapshot_is_derived_from_selected_ppmp_item(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $category = ItemCategory::query()->create([
            'category_name' => 'Snapshot Category',
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Snapshot Paper',
            'description' => 'Snapshot test item',
            'default_unit' => 'REAM',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 20,
            'unit_cost' => 35,
            'estimated_budget' => 700,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        $request = $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Snapshot test request',
            'priority_level' => 'MEDIUM',
            'review_notes' => null,
        ]);

        $request->assertRedirect();
        $supplyRequest = SupplyRequest::query()->firstOrFail();

        $this->actingAs($officeUser)->post(route('office.supply-requests.items.store', $supplyRequest->supply_request_id), [
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Tampered Snapshot Value',
            'quantity_requested' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('supply_request_items', [
            'supply_request_id' => $supplyRequest->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Snapshot Paper',
            'quantity_requested' => 4,
        ]);

        $requestItem = SupplyRequestItem::query()->firstOrFail();

        $this->actingAs($officeUser)->put(route('office.supply-requests.items.update', [$supplyRequest->supply_request_id, $requestItem->supply_request_item_id]), [
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Updated Tampered Snapshot',
            'quantity_requested' => 6,
        ])->assertRedirect();

        $this->assertDatabaseHas('supply_request_items', [
            'supply_request_item_id' => $requestItem->supply_request_item_id,
            'item_name_snapshot' => 'Snapshot Paper',
            'quantity_requested' => 6,
        ]);
    }

    public function test_supply_request_create_page_only_lists_approved_ppmps(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $otherOffice = Office::query()->create([
            'name' => 'Other Office',
            'code' => 'OTHER_OFF_1',
            'description' => 'Other Office description',
            'is_active' => true,
        ]);

        $approved = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        Ppmp::query()->create([
            'office_id' => $otherOffice->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Create')
                ->has('ppmpOptions', 1)
                ->where('ppmpOptions.0.ppmp_id', $approved->ppmp_id)
                ->where('ppmpOptions.0.status', 'APPROVED')
            );
    }

    public function test_supply_request_create_page_lists_selected_approved_ppmp_items_case_insensitively(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $category = ItemCategory::query()->create([
            'category_name' => 'Case Sensitive Category',
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Trash Bag',
            'description' => 'Case-insensitive status item',
            'default_unit' => 'roll',
            'status' => 'ACTIVE',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 100,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 10,
            'estimated_budget' => 100,
            'procurement_schedule' => 'Q4',
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.create', ['ppmp_id' => $ppmp->ppmp_id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Create')
                ->where('selectedPpmpId', $ppmp->ppmp_id)
                ->has('ppmpItemOptions', 1)
                ->where('ppmpItemOptions.0.ppmp_item_id', $ppmpItem->ppmp_item_id)
                ->where('ppmpItemOptions.0.item_name', 'Trash Bag')
                ->where('ppmpItemOptions.0.remaining_quantity', 10)
            );

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.ppmp-items', $ppmp->ppmp_id))
            ->assertOk()
            ->assertJsonPath('ppmp_item_options.0.ppmp_item_id', $ppmpItem->ppmp_item_id)
            ->assertJsonPath('ppmp_item_options.0.item_name', 'Trash Bag');
    }

    public function test_supply_request_create_page_shows_blocked_state_when_no_approved_ppmp_exists(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Create')
                ->has('ppmpOptions', 0)
            );
    }

    public function test_supply_request_store_rejects_submitted_and_cross_office_ppmp_sources(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $otherOffice = Office::query()->create([
            'name' => 'Other Office',
            'code' => 'OTHER_OFF_2',
            'description' => 'Other Office description',
            'is_active' => true,
        ]);

        $submitted = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $crossOfficeApproved = Ppmp::query()->create([
            'office_id' => $otherOffice->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->post(route('office.supply-requests.store'), [
                'ppmp_id' => $submitted->ppmp_id,
                'needed_by_date' => now()->addDays(2)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Submitted PPMP test',
                'priority_level' => 'MEDIUM',
                'review_notes' => null,
                'items' => [],
            ])
            ->assertSessionHasErrors('ppmp_id');

        $this->actingAs($officeUser)
            ->post(route('office.supply-requests.store'), [
                'ppmp_id' => $crossOfficeApproved->ppmp_id,
                'needed_by_date' => now()->addDays(2)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Cross office PPMP test',
                'priority_level' => 'MEDIUM',
                'review_notes' => null,
                'items' => [],
            ])
            ->assertSessionHasErrors('ppmp_id');

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.ppmp-items', $submitted->ppmp_id))
            ->assertForbidden();
    }

    public function test_supply_request_update_rejects_non_approved_ppmp_source(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $approved = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $submitted = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-APPROVED-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => $approved->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Approved source test',
            'priority_level' => 'MEDIUM',
            'status' => 'DRAFT',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($officeUser)
            ->put(route('office.supply-requests.update', $supplyRequest->supply_request_id), [
                'ppmp_id' => $submitted->ppmp_id,
                'needed_by_date' => now()->addDays(5)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Attempted tamper',
                'priority_level' => 'HIGH',
                'review_notes' => 'Tamper',
            ])
            ->assertSessionHasErrors('ppmp_id');
    }

    public function test_request_tracking_filters_by_resolved_procurement_placed_status(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Placed Supplier',
            'status' => 'ACTIVE',
        ]);

        $placedSupplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-PLACED-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Placed procurement status filter test',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $draftSupplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-DRAFT-PO-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Draft PO status filter control',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $placedPurchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $placedSupplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);
        $draftPurchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $draftSupplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);

        PurchaseOrder::query()->create([
            'purchase_request_id' => $placedPurchaseRequest->purchase_request_id,
            'supply_request_id' => $placedSupplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $officeUser->id,
            'po_number' => 'PO-PLACED-1',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 100,
            'terms_notes' => null,
            'remarks' => null,
        ]);
        PurchaseOrder::query()->create([
            'purchase_request_id' => $draftPurchaseRequest->purchase_request_id,
            'supply_request_id' => $draftSupplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $officeUser->id,
            'po_number' => 'PO-DRAFT-1',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'DRAFT',
            'expected_delivery_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 100,
            'terms_notes' => null,
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.index', ['status' => 'PROCUREMENT_PLACED']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Index')
                ->where('filters.status', 'PROCUREMENT_PLACED')
                ->has('supplyRequests.data', 1)
                ->where('supplyRequests.data.0.request_code', 'SR-PLACED-1')
                ->where('supplyRequests.data.0.status', 'PROCUREMENT_PLACED')
            );
    }

    public function test_office_supply_request_show_exposes_returned_procurement_reason(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-RETURNED-PR-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Returned PR visibility test',
            'priority_level' => 'MEDIUM',
            'status' => 'DRAFT',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementOfficer->id,
            'status' => 'RETURNED_FOR_REVISION',
            'remarks' => 'BAC requires corrected quantities and specifications.',
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Show')
                ->where('supplyRequest.status', 'RETURNED_FOR_REVISION')
                ->has('supplyRequest.purchase_requests', 1)
                ->where('supplyRequest.purchase_requests.0.purchase_request_id', $purchaseRequest->purchase_request_id)
                ->where('supplyRequest.purchase_requests.0.status', 'RETURNED_FOR_REVISION')
                ->where('supplyRequest.purchase_requests.0.remarks', 'BAC requires corrected quantities and specifications.')
            );
    }

    public function test_resubmitted_supply_request_ignores_stale_returned_purchase_request_status(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-RESUBMITTED-PR-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Resubmitted returned PR status test',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => 'Office resubmitted after revision.',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementOfficer->id,
            'status' => 'RETURNED_FOR_REVISION',
            'remarks' => 'Previous return reason.',
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Show')
                ->where('supplyRequest.status', 'SUBMITTED')
                ->where('supplyRequest.raw_status', 'SUBMITTED')
                ->has('supplyRequest.purchase_requests', 1)
                ->where('supplyRequest.purchase_requests.0.status', 'RETURNED_FOR_REVISION')
            );

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.index', ['status' => 'RETURNED_FOR_REVISION']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Index')
                ->where('filters.status', 'RETURNED_FOR_REVISION')
                ->has('supplyRequests.data', 0)
            );
    }

    public function test_request_with_only_partial_received_distribution_shows_partial_received_tracking_status(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        [$ppmp, $ppmpItem, $item] = $this->createApprovedPpmpItemContext($office, $officeUser, 500);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-PARTIAL-RECEIPT-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Partial receipt status test',
            'priority_level' => 'MEDIUM',
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
            'quantity_requested' => 500,
        ]);

        $distributionRecord = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-PARTIAL-1',
            'supply_request_id' => $supplyRequest->supply_request_id,
            'delivery_id' => null,
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'received_by' => $officeUser->id,
            'release_date' => now()->toDateString(),
            'storage_location' => 'GSO',
            'status' => 'RECEIVED',
            'notes' => null,
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $distributionRecord->distribution_record_id,
            'item_id' => $item->item_id,
            'received_by' => $officeUser->id,
            'item_name_snapshot' => $item->item_name,
            'quantity_released' => 8,
            'quantity_received' => 8,
            'quantity_damaged' => 0,
            'quantity_missing' => 0,
            'receipt_condition' => 'GOOD',
            'unit' => 'PCS',
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Show')
                ->where('supplyRequest.status', 'PARTIALLY_RECEIVED')
                ->where('supplyRequest.items.0.quantity_requested', 500)
                ->where('supplyRequest.items.0.quantity_released', 8)
                ->where('supplyRequest.items.0.quantity_received', 8)
            );
    }

    public function test_original_supply_request_counts_linked_follow_up_distribution_as_fulfillment(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);
        [$ppmp, $ppmpItem, $item] = $this->createApprovedPpmpItemContext($office, $officeUser, 50);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Follow-up Fulfillment Supplier',
            'contact_person' => 'Contact',
            'email' => uniqid('supplier', true).'@test.local',
            'phone' => '09123456789',
            'address' => 'Address',
            'status' => 'ACTIVE',
        ]);

        $originalRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-FOLLOW-UP-FULFILL-ORIG',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Original 50 item request',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $originalRequest->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 50,
        ]);

        $originalPurchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $originalRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementOfficer->id,
            'status' => 'PROCESSING',
            'remarks' => 'Original procurement.',
        ]);

        $originalPurchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $originalPurchaseRequest->purchase_request_id,
            'supply_request_id' => $originalRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementOfficer->id,
            'po_number' => 'PO-FOLLOW-UP-FULFILL-ORIG',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 500,
        ]);

        $originalDelivery = Delivery::query()->create([
            'delivery_code' => 'DEL-FOLLOW-UP-FULFILL-ORIG',
            'purchase_order_id' => $originalPurchaseOrder->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->addDay()->toDateString(),
            'received_by' => $gsoOfficer->id,
            'receipt_status' => 'VERIFIED',
            'remarks' => 'Original delivery accepted only 40 of 50.',
        ]);

        $originalDistribution = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-FOLLOW-UP-FULFILL-ORIG',
            'supply_request_id' => $originalRequest->supply_request_id,
            'delivery_id' => $originalDelivery->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoOfficer->id,
            'received_by' => $officeUser->id,
            'release_date' => now()->toDateString(),
            'storage_location' => 'GSO',
            'status' => 'RECEIVED',
            'notes' => null,
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $originalDistribution->distribution_record_id,
            'item_id' => $item->item_id,
            'received_by' => $officeUser->id,
            'item_name_snapshot' => $item->item_name,
            'quantity_released' => 40,
            'quantity_received' => 40,
            'quantity_damaged' => 0,
            'quantity_missing' => 0,
            'receipt_condition' => 'GOOD',
            'unit' => 'PCS',
            'remarks' => null,
        ]);

        $followUpRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-FOLLOW-UP-FULFILL-10',
            'office_id' => $office->id,
            'requested_by' => $gsoOfficer->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Follow-up procurement for missing 10',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => 'Generated from original delivery discrepancy.',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $followUpRequest->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 10,
        ]);

        $followUpPurchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $followUpRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoOfficer->id,
            'source_delivery_id' => $originalDelivery->delivery_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'PROCESSING',
            'remarks' => 'Generated from original delivery discrepancy.',
        ]);

        $followUpPurchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $followUpPurchaseRequest->purchase_request_id,
            'supply_request_id' => $followUpRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementOfficer->id,
            'po_number' => 'PO-FOLLOW-UP-FULFILL-10',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 100,
        ]);

        $followUpDelivery = Delivery::query()->create([
            'delivery_code' => 'DEL-FOLLOW-UP-FULFILL-10',
            'purchase_order_id' => $followUpPurchaseOrder->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->addDay()->toDateString(),
            'received_by' => $gsoOfficer->id,
            'receipt_status' => 'VERIFIED',
            'remarks' => 'Follow-up delivery accepted 10.',
        ]);

        $followUpDistribution = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-FOLLOW-UP-FULFILL-10',
            'supply_request_id' => $followUpRequest->supply_request_id,
            'delivery_id' => $followUpDelivery->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoOfficer->id,
            'received_by' => $officeUser->id,
            'release_date' => now()->toDateString(),
            'storage_location' => 'GSO',
            'status' => 'RECEIVED',
            'notes' => null,
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $followUpDistribution->distribution_record_id,
            'item_id' => $item->item_id,
            'received_by' => $officeUser->id,
            'item_name_snapshot' => $item->item_name,
            'quantity_released' => 10,
            'quantity_received' => 10,
            'quantity_damaged' => 0,
            'quantity_missing' => 0,
            'receipt_condition' => 'GOOD',
            'unit' => 'PCS',
            'remarks' => null,
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $originalRequest->supply_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Show')
                ->where('supplyRequest.status', 'RECEIVED')
                ->where('supplyRequest.items.0.quantity_requested', 50)
                ->where('supplyRequest.items.0.quantity_released', 50)
                ->where('supplyRequest.items.0.quantity_received', 50)
                ->has('distributionRecords', 2)
                ->where('distributionRecords.0.is_follow_up_fulfillment', true)
                ->where('distributionRecords.1.is_follow_up_fulfillment', false)
            );
    }

    public function test_partial_delivery_follow_up_supply_request_does_not_double_count_ppmp_consumption(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $office);
        [$ppmp, $ppmpItem, $item] = $this->createApprovedPpmpItemContext($office, $officeUser, 500);

        $originalRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-ORIGINAL-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Original request',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $originalRequest->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 500,
        ]);

        $followUpRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-FOLLOW-UP-1',
            'office_id' => $office->id,
            'requested_by' => $gsoOfficer->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Follow-up procurement for missing delivery',
            'priority_level' => 'MEDIUM',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $followUpRequest->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 10,
        ]);

        PurchaseRequest::query()->create([
            'supply_request_id' => $followUpRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoOfficer->id,
            'source_delivery_id' => null,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'OPEN',
            'remarks' => 'Generated from discrepancy follow-up.',
        ]);

        $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $originalRequest->supply_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/SupplyRequests/Show')
                ->where('supplyRequest.items.0.already_requested_quantity', 500)
                ->where('supplyRequest.items.0.remaining_quantity', 0)
                ->where('supplyRequest.items.0.exceeds_ppmp_allocation', false)
            );
    }

    public function test_supply_request_create_requires_ppmp_id_matching_line_items(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp1 = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem1 = PpmpItem::query()->create([
            'ppmp_id' => $ppmp1->ppmp_id,
            'item_id' => 100,
            'item_category_id' => 10,
            'quantity' => 10,
            'unit_cost' => 100,
            'estimated_budget' => 1000,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        // Missing ppmp_id should fail validation
        $create = $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Need items',
            'priority_level' => 'MEDIUM',
            'items' => [[
                'item_id' => 100,
                'ppmp_item_id' => $ppmpItem1->ppmp_item_id,
                'item_name_snapshot' => 'Item 100',
                'quantity_requested' => 5,
            ]],
        ]);
        $create->assertSessionHasErrors('ppmp_id');
    }

    public function test_supply_request_rejects_mismatched_ppmp_item_reference_for_selected_ppmp(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmpA = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpB = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $foreignPpmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmpB->ppmp_id,
            'item_id' => 201,
            'item_category_id' => 20,
            'quantity' => 5,
            'unit_cost' => 100,
            'estimated_budget' => 500,
            'procurement_schedule' => 'Q2',
            'remarks' => null,
        ]);

        $response = $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $ppmpA->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Mismatched PPMP item test',
            'priority_level' => 'HIGH',
            'items' => [[
                'item_id' => 201,
                'ppmp_item_id' => $foreignPpmpItem->ppmp_item_id,
                'item_name_snapshot' => 'Item 201',
                'quantity_requested' => 3,
            ]],
        ]);

        $response->assertSessionHasErrors('items.0.ppmp_item_id');
    }

    public function test_supply_request_allows_over_request_and_flags_submission_for_executive_review(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 200,
            'item_category_id' => 20,
            'quantity' => 5,
            'unit_cost' => 100,
            'estimated_budget' => 500,
            'procurement_schedule' => 'Q2',
            'remarks' => null,
        ]);

        $create = $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Too much',
            'priority_level' => 'HIGH',
            'items' => [[
                'item_id' => 200,
                'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => 'Item 200',
                'quantity_requested' => 10,
            ]],
        ]);
        $create->assertRedirect();

        $supplyRequest = SupplyRequest::query()->firstOrFail();

        $this->assertDatabaseHas('supply_request_items', [
            'supply_request_id' => $supplyRequest->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'quantity_requested' => 10,
        ]);

        $submitResponse = $this->actingAs($officeUser)->post(route('office.supply-requests.submit', $supplyRequest->supply_request_id), [
            'review_notes' => 'Urgent request',
        ]);

        $submitResponse->assertRedirect(route('office.supply-requests.show', $supplyRequest->supply_request_id));
        $submitResponse->assertSessionHas('success');

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'PPMP_OVER_REQUEST',
            'reference_type' => SupplyRequestItem::class,
            'office_id' => $office->id,
            'detection_method' => 'PPMP_PLAN_CONSUMPTION',
            'status' => 'OPEN',
        ]);

        $anomaly = Anomaly::query()
            ->where('anomaly_type', 'PPMP_OVER_REQUEST')
            ->where('office_id', $office->id)
            ->firstOrFail();

        $this->assertSame('HIGH', $anomaly->severity);
        $this->assertGreaterThan((float) $anomaly->expected_value, (float) $anomaly->detected_value);
    }

    public function test_supply_request_can_be_submitted_directly_after_create(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $office);
        [$ppmp, $ppmpItem, $item] = $this->createApprovedPpmpItemContext($office, $officeUser, 20);

        $response = $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $ppmp->ppmp_id,
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Direct submit from create page',
            'priority_level' => 'MEDIUM',
            'review_notes' => 'Submit immediately',
            'submit_after_save' => true,
            'items' => [[
                'item_id' => $item->item_id,
                'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => $item->item_name,
                'quantity_requested' => 5,
            ]],
        ]);

        $supplyRequest = SupplyRequest::query()->firstOrFail();

        $response->assertRedirect(route('office.supply-requests.show', $supplyRequest->supply_request_id));
        $response->assertSessionHas('success', 'Supply request submitted successfully.');
        $this->assertSame('SUBMITTED', $supplyRequest->refresh()->status);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'new_status' => 'SUBMITTED',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $gsoOfficer->id,
            'notification_type' => 'GSO_SUPPLY_REQUEST_SUBMITTED',
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'is_read' => false,
        ]);
    }

    public function test_draft_supply_request_can_be_submitted_from_tracking_list(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        [$ppmp, $ppmpItem, $item] = $this->createApprovedPpmpItemContext($office, $officeUser, 20);

        $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $ppmp->ppmp_id,
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Submit from tracking list',
            'priority_level' => 'MEDIUM',
            'items' => [[
                'item_id' => $item->item_id,
                'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => $item->item_name,
                'quantity_requested' => 5,
            ]],
        ])->assertRedirect();

        $supplyRequest = SupplyRequest::query()->firstOrFail();

        $response = $this
            ->actingAs($officeUser)
            ->from(route('office.supply-requests.index', ['status' => 'DRAFT']))
            ->post(route('office.supply-requests.submit', $supplyRequest->supply_request_id), [
                'redirect_to' => 'index',
            ]);

        $response->assertRedirect(route('office.supply-requests.index', ['status' => 'DRAFT']));
        $response->assertSessionHas('success', 'Supply request submitted successfully.');
        $this->assertSame('SUBMITTED', $supplyRequest->refresh()->status);
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Office A',
            'code' => 'OFFICE_A',
            'description' => 'Office A',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for office transactional flow tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'emp_id' => sprintf('OFF-%d-%s', $office->id, strtoupper(substr((string) str()->uuid(), 0, 8))),
        ]);
    }

    private function createApprovedPpmpItemContext(Office $office, User $officeUser, float $plannedQuantity): array
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'standard_delivery_days' => 7,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Rubbing Alcohol',
            'description' => null,
            'default_unit' => 'PCS',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => $plannedQuantity,
            'unit_cost' => 100,
            'estimated_budget' => $plannedQuantity * 100,
            'procurement_schedule' => 'Q2',
            'remarks' => 'Required specification for Rubbing Alcohol from seeded PPMP item remarks.',
        ]);

        return [$ppmp, $ppmpItem, $item];
    }
}
