<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Office $officeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->officeA = Office::query()->create([
            'name' => 'Office A',
            'code' => 'OFFICE_A',
            'description' => 'Test office A',
            'is_active' => true,
        ]);
    }

    public function test_domain_routes_are_owned_by_single_roles_and_wrong_roles_are_denied(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $officeUser = $this->createUser(UserRole::OFFICE_USER);
        $procOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER);
        $gsoOfficer = $this->createUser(UserRole::GSO_OFFICER);
        $execOfficer = $this->createUser(UserRole::EXECUTIVE_OFFICER);
        $publicUser = $this->createUser(UserRole::PUBLIC);

        $this->actingAs($admin)->get('/admin-tech')->assertOk();
        $this->actingAs($officeUser)->get('/office')->assertOk();
        $this->actingAs($procOfficer)->get('/procurement')->assertOk();
        $this->actingAs($gsoOfficer)->get('/inventory')->assertOk();
        $this->actingAs($execOfficer)->get('/executive')->assertOk();

        $internalRoutes = ['/admin-tech', '/office', '/procurement', '/inventory', '/executive'];

        foreach ($internalRoutes as $route) {
            $this->actingAs($publicUser)->get($route)->assertForbidden();
        }

        $usersByRole = [
            UserRole::ADMIN->value => $admin,
            UserRole::OFFICE_USER->value => $officeUser,
            UserRole::PROCUREMENT_OFFICER->value => $procOfficer,
            UserRole::GSO_OFFICER->value => $gsoOfficer,
            UserRole::EXECUTIVE_OFFICER->value => $execOfficer,
        ];

        $ownedRouteByRole = [
            UserRole::ADMIN->value => '/admin-tech',
            UserRole::OFFICE_USER->value => '/office',
            UserRole::PROCUREMENT_OFFICER->value => '/procurement',
            UserRole::GSO_OFFICER->value => '/inventory',
            UserRole::EXECUTIVE_OFFICER->value => '/executive',
        ];

        foreach ($usersByRole as $roleCode => $user) {
            foreach ($ownedRouteByRole as $ownerRole => $ownedRoute) {
                if ($roleCode === $ownerRole) {
                    continue;
                }

                $this->actingAs($user)->get($ownedRoute)->assertForbidden();
            }
        }
    }

    public function test_public_dashboard_is_reachable_by_public_and_guests_only_as_transparency_scaffold(): void
    {
        $publicUser = $this->createUser(UserRole::PUBLIC);

        $this->actingAs($publicUser)->get('/public')->assertOk();
        $this->get('/public')->assertOk();
    }

    public function test_deny_by_default_for_undefined_abilities(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);

        $this->assertFalse($admin->can('domain.undefined.access'));
        $this->assertFalse($admin->can('analytics.undefined.view'));
    }

    private function createUser(UserRole $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for authorization tests',
                'is_active' => true,
            ],
        );

        return User::factory()
            ->withRole($role)
            ->forOffice($this->officeA)
            ->create([
                'role_id' => $roleModel->id,
            ]);
    }
}
