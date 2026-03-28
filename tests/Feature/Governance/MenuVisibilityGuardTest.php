<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MenuVisibilityGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_shared_authorization_flags_match_domain_ownership(): void
    {
        $office = Office::query()->create([
            'name' => 'Main Office',
            'code' => 'MAIN_OFFICE',
            'description' => 'Main test office',
            'is_active' => true,
        ]);

        $admin = $this->createUser(UserRole::ADMIN, $office);
        $inventoryOfficer = $this->createUser(UserRole::GSO_OFFICER, $office);

        $middleware = new HandleInertiaRequests();

        $adminRequest = Request::create('/profile', 'GET');
        $adminRequest->setUserResolver(fn (): User => $admin);

        $adminPayload = $middleware->share($adminRequest);

        $this->assertTrue(data_get($adminPayload, 'auth.can.admin_tech'));
        $this->assertFalse(data_get($adminPayload, 'auth.can.office'));
        $this->assertFalse(data_get($adminPayload, 'auth.can.procurement'));
        $this->assertFalse(data_get($adminPayload, 'auth.can.inventory'));
        $this->assertFalse(data_get($adminPayload, 'auth.can.executive'));

        $inventoryRequest = Request::create('/profile', 'GET');
        $inventoryRequest->setUserResolver(fn (): User => $inventoryOfficer);

        $inventoryPayload = $middleware->share($inventoryRequest);

        $this->assertFalse(data_get($inventoryPayload, 'auth.can.admin_tech'));
        $this->assertFalse(data_get($inventoryPayload, 'auth.can.office'));
        $this->assertFalse(data_get($inventoryPayload, 'auth.can.procurement'));
        $this->assertTrue(data_get($inventoryPayload, 'auth.can.inventory'));
        $this->assertFalse(data_get($inventoryPayload, 'auth.can.executive'));
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for menu-guard tests',
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
