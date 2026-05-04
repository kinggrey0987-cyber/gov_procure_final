<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeTransactionalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_office_user_can_manage_only_own_office_ppmp_records(): void
    {
        [$officeA, $officeB] = $this->createOffices();

        $owner = $this->createUser(UserRole::OFFICE_USER, $officeA);
        $otherOfficeUser = $this->createUser(UserRole::OFFICE_USER, $officeB);

        $ppmp = Ppmp::query()->create([
            'office_id' => $officeA->id,
            'prepared_by' => $owner->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $this->actingAs($owner)->get(route('office.ppmp.show', $ppmp->ppmp_id))->assertOk();
        $this->actingAs($otherOfficeUser)->get(route('office.ppmp.show', $ppmp->ppmp_id))->assertForbidden();
    }

    public function test_office_user_can_manage_only_own_office_supply_requests(): void
    {
        [$officeA, $officeB] = $this->createOffices();

        $owner = $this->createUser(UserRole::OFFICE_USER, $officeA);
        $otherOfficeUser = $this->createUser(UserRole::OFFICE_USER, $officeB);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-TEST-1',
            'office_id' => $officeA->id,
            'requested_by' => $owner->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Office supplies',
            'priority_level' => 'MEDIUM',
            'status' => 'DRAFT',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($owner)->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))->assertOk();
        $this->actingAs($otherOfficeUser)->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))->assertForbidden();
    }

    public function test_admin_cannot_access_office_transactional_routes(): void
    {
        $office = Office::query()->create([
            'name' => 'Admin Office',
            'code' => 'ADMIN_OFF',
            'description' => 'Admin office',
            'is_active' => true,
        ]);

        $admin = $this->createUser(UserRole::ADMIN, $office);

        $this->actingAs($admin)->get(route('office.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('office.ppmp.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('office.supply-requests.index'))->assertForbidden();
    }

    public function test_non_office_user_role_cannot_mutate_ppmp_item_lines(): void
    {
        $office = Office::query()->create([
            'name' => 'Office Line Access',
            'code' => 'OFF_LINE',
            'description' => 'Office line access test office',
            'is_active' => true,
        ]);

        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $admin = $this->createUser(UserRole::ADMIN, $office);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'DRAFT',
            'total_estimated_budget' => 200,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $item = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 501,
            'item_category_id' => 50,
            'quantity' => 2,
            'unit_cost' => 100,
            'estimated_budget' => 200,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('office.ppmp.items.update', [$ppmp->ppmp_id, $item->ppmp_item_id]), [
                'item_id' => 501,
                'item_category_id' => 50,
                'quantity' => 4,
                'unit_cost' => 100,
                'procurement_schedule' => 'Q2',
                'remarks' => 'Unauthorized update attempt',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('office.ppmp.items.destroy', [$ppmp->ppmp_id, $item->ppmp_item_id]))
            ->assertForbidden();
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for office transactional tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }

    /**
     * @return array{0: Office, 1: Office}
     */
    private function createOffices(): array
    {
        $officeA = Office::query()->create([
            'name' => 'Office A',
            'code' => 'OFFICE_A',
            'description' => 'Office A',
            'is_active' => true,
        ]);

        $officeB = Office::query()->create([
            'name' => 'Office B',
            'code' => 'OFFICE_B',
            'description' => 'Office B',
            'is_active' => true,
        ]);

        return [$officeA, $officeB];
    }
}
