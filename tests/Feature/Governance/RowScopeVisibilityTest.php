<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RowScopeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Office $officeA;

    private Office $officeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeA = Office::query()->create([
            'name' => 'Office A',
            'code' => 'OFFICE_A',
            'description' => 'Test office A',
            'is_active' => true,
        ]);

        $this->officeB = Office::query()->create([
            'name' => 'Office B',
            'code' => 'OFFICE_B',
            'description' => 'Test office B',
            'is_active' => true,
        ]);
    }

    public function test_office_user_is_limited_to_own_office_scope(): void
    {
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $this->officeA);

        $this->assertTrue($officeUser->can('scope.own-office', $this->officeA->id));
        $this->assertFalse($officeUser->can('scope.own-office', $this->officeB->id));
    }

    public function test_admin_cannot_access_business_domains_even_when_authenticated(): void
    {
        $admin = $this->createUser(UserRole::ADMIN, $this->officeA);

        $this->actingAs($admin)->get('/office')->assertForbidden();
        $this->actingAs($admin)->get('/procurement')->assertForbidden();
        $this->actingAs($admin)->get('/inventory')->assertForbidden();
        $this->actingAs($admin)->get('/executive')->assertForbidden();
    }

    public function test_executive_only_visibility_scaffolds_are_restricted_correctly(): void
    {
        $execOfficer = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->officeA);
        $gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->officeA);

        $this->actingAs($execOfficer)->get('/executive')->assertOk();
        $this->assertTrue($execOfficer->can('analytics.anomalies.view'));
        $this->assertTrue($execOfficer->can('audit.business.view'));
        $this->assertFalse($execOfficer->can('analytics.forecasting.view'));

        $this->assertFalse($gsoOfficer->can('analytics.anomalies.view'));
        $this->assertFalse($gsoOfficer->can('audit.business.view'));
    }

    public function test_gso_only_inventory_visibility_scaffolds_are_restricted_correctly(): void
    {
        $gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->officeA);
        $execOfficer = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->officeA);

        $this->actingAs($gsoOfficer)->get('/inventory')->assertOk();
        $this->assertTrue($gsoOfficer->can('analytics.forecasting.view'));
        $this->assertTrue($gsoOfficer->can('analytics.reorder-alerts.view'));
        $this->assertTrue($gsoOfficer->can('analytics.delayed-delivery.view'));
        $this->assertTrue($gsoOfficer->can('analytics.inventory.view'));

        $this->assertFalse($execOfficer->can('analytics.forecasting.view'));
        $this->assertFalse($execOfficer->can('analytics.reorder-alerts.view'));
        $this->assertFalse($execOfficer->can('analytics.delayed-delivery.view'));
        $this->assertFalse($execOfficer->can('analytics.inventory.view'));
    }

    public function test_public_can_only_access_public_disclosure_scaffold(): void
    {
        $publicUser = $this->createUser(UserRole::PUBLIC, $this->officeA);

        $this->actingAs($publicUser)->get('/public')->assertOk();
        $this->actingAs($publicUser)->get('/admin-tech')->assertForbidden();
        $this->actingAs($publicUser)->get('/office')->assertForbidden();
        $this->actingAs($publicUser)->get('/procurement')->assertForbidden();
        $this->actingAs($publicUser)->get('/inventory')->assertForbidden();
        $this->actingAs($publicUser)->get('/executive')->assertForbidden();
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for row-scope tests',
                'is_active' => true,
            ],
        );

        return User::factory()
            ->withRole($role)
            ->forOffice($office)
            ->create([
                'role_id' => $roleModel->id,
                'office_id' => $office->id,
            ]);
    }
}
