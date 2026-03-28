<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\Role;
use App\Models\StatusHistory;
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

        $create = $this->actingAs($officeUser)->post(route('office.ppmp.store'), [
            'fiscal_year' => 2026,
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
            'fund_source' => 'General Fund',
            'remarks' => 'Item row',
        ])->assertRedirect();

        $this->actingAs($officeUser)->put(route('office.ppmp.update', $ppmp->ppmp_id), [
            'fiscal_year' => 2027,
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
    }

    public function test_supply_request_create_update_submit_writes_status_history(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
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
            'fund_source' => 'General Fund',
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
            'estimated_unit_cost' => 50,
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

    public function test_supply_request_create_requires_ppmp_id_matching_line_items(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp1 = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
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
            'fund_source' => 'General Fund',
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
                'estimated_unit_cost' => 100,
            ]],
        ]);
        $create->assertSessionHasErrors('ppmp_id');
    }

    public function test_supply_request_blocks_over_request_against_ppmp_planned_quantity(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
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
            'fund_source' => 'General Fund',
            'remarks' => null,
        ]);

        // Request exceeds planned quantity. Should fail.
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
                'estimated_unit_cost' => 100,
            ]],
        ]);
        $create->assertSessionHasErrors('items.0.quantity_requested');
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
        ]);
    }
}
