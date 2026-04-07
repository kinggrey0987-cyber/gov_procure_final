<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

class DashboardPostLoginStabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_redirects_to_expected_domain_for_each_role(): void
    {
        $office = $this->createOffice();

        $this->assertDashboardRedirect(UserRole::ADMIN, 'admin-tech.dashboard', $office);
        $this->assertDashboardRedirect(UserRole::OFFICE_USER, 'office.dashboard', $office);
        $this->assertDashboardRedirect(UserRole::PROCUREMENT_OFFICER, 'procurement.dashboard', $office);
        $this->assertDashboardRedirect(UserRole::GSO_OFFICER, 'inventory.dashboard', $office);
        $this->assertDashboardRedirect(UserRole::EXECUTIVE_OFFICER, 'executive.dashboard', $office);
        $this->assertDashboardRedirect(UserRole::PUBLIC, 'public.dashboard', $office);
    }

    public function test_dashboard_routes_load_without_crashing_with_zero_domain_data(): void
    {
        $office = $this->createOffice();

        $this->actingAs($this->createUser(UserRole::ADMIN, $office))
            ->get(route('admin-tech.dashboard'))
            ->assertOk();

        $this->actingAs($this->createUser(UserRole::OFFICE_USER, $office))
            ->get(route('office.dashboard'))
            ->assertOk();

        $this->actingAs($this->createUser(UserRole::PROCUREMENT_OFFICER, $office))
            ->get(route('procurement.dashboard'))
            ->assertOk();

        $this->actingAs($this->createUser(UserRole::GSO_OFFICER, $office))
            ->get(route('inventory.dashboard'))
            ->assertOk();

        $this->actingAs($this->createUser(UserRole::EXECUTIVE_OFFICER, $office))
            ->get(route('executive.dashboard'))
            ->assertOk();

        $this->actingAs($this->createUser(UserRole::PUBLIC, $office))
            ->get(route('public.dashboard'))
            ->assertOk();
    }

    public function test_admin_cannot_access_executive_or_procurement_dashboards(): void
    {
        $office = $this->createOffice();
        $admin = $this->createUser(UserRole::ADMIN, $office);

        $this->actingAs($admin)->get(route('executive.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.dashboard'))->assertForbidden();
    }

    public function test_authenticated_dashboard_load_survives_notification_service_failures(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $mock = Mockery::mock(NotificationCenterService::class);
        $mock->shouldReceive('recentForUser')->andThrow(new \RuntimeException('notification backend unavailable'));
        $mock->shouldReceive('unreadCountForUser')->andThrow(new \RuntimeException('notification backend unavailable'));
        $this->app->instance(NotificationCenterService::class, $mock);

        $this->actingAs($officeUser)
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.unread_count', 0)
                ->where('notifications.recent', [])
            );
    }

    public function test_dashboard_fallback_for_user_with_unrecognized_role_is_safe_and_non_looping(): void
    {
        $office = $this->createOffice();

        $unknownRole = Role::query()->create([
            'code' => 'UNKNOWN_ROLE',
            'name' => 'Unknown Role',
            'description' => 'Unrecognized role for fallback test',
            'is_active' => true,
        ]);

        $user = User::factory()->forOffice($office)->create([
            'role_id' => $unknownRole->id,
            'office_id' => $office->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function assertDashboardRedirect(UserRole $role, string $expectedRoute, Office $office): void
    {
        $user = $this->createUser($role, $office);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route($expectedRoute));
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Dashboard Test Office '.uniqid(),
            'code' => 'DTO_'.uniqid(),
            'description' => 'Office for dashboard stability tests',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for dashboard stability tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
