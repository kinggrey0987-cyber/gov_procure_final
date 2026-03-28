<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementFoundationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_procurement_officer_can_access_procurement_foundation_routes(): void
    {
        $office = $this->createOffice();
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $this->actingAs($procurementOfficer)->get(route('procurement.dashboard'))->assertOk();
        $this->actingAs($procurementOfficer)->get(route('procurement.purchase-requests.index'))->assertOk();
        $this->actingAs($procurementOfficer)->get(route('procurement.suppliers.index'))->assertOk();
        $this->actingAs($procurementOfficer)->get(route('procurement.purchase-orders.index'))->assertOk();
    }

    public function test_office_user_cannot_manage_procurement_or_supplier_records(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $this->actingAs($officeUser)->get(route('procurement.purchase-requests.index'))->assertForbidden();
        $this->actingAs($officeUser)->get(route('procurement.suppliers.index'))->assertForbidden();
        $this->actingAs($officeUser)->post(route('procurement.suppliers.store'), [
            'supplier_name' => 'Not Allowed Supplier',
            'status' => 'ACTIVE',
        ])->assertForbidden();
    }

    public function test_admin_cannot_access_procurement_business_routes(): void
    {
        $office = $this->createOffice();
        $admin = $this->createUser(UserRole::ADMIN, $office);

        $this->actingAs($admin)->get(route('procurement.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.purchase-requests.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.suppliers.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.purchase-orders.index'))->assertForbidden();
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Procurement Office',
            'code' => 'PROC_OFFICE',
            'description' => 'Procurement test office',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for procurement feature tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
