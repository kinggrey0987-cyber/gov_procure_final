<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GovernanceFoundationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_codes_are_immutable(): void
    {
        $role = Role::query()->create([
            'name' => 'Role One',
            'code' => 'ROLE_ONE',
            'description' => 'Test role',
            'is_active' => true,
        ]);

        $this->expectException(\LogicException::class);

        $role->update([
            'code' => 'ROLE_TWO',
        ]);
    }

    public function test_canonical_roles_cannot_be_deleted_after_seeding(): void
    {
        $this->seed(RoleSeeder::class);

        $canonicalCodes = array_map(
            static fn (UserRole $enum): string => $enum->value,
            UserRole::cases(),
        );

        $this->assertEqualsCanonicalizing(
            $canonicalCodes,
            Role::query()->pluck('code')->all()
        );

        $canonicalRole = Role::query()->where('code', UserRole::ADMIN->value)->firstOrFail();

        $this->expectException(\LogicException::class);

        $canonicalRole->delete();
    }

    public function test_no_superuser_fallback_logic_exists_in_provider(): void
    {
        $providerFile = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertIsString($providerFile);
        $this->assertStringNotContainsString('Gate::before', $providerFile);
        $this->assertStringNotContainsString('superadmin', strtolower($providerFile));
    }

    public function test_internal_domain_routes_are_protected_by_auth_and_domain_middleware(): void
    {
        $routeNames = [
            'admin-tech.dashboard' => 'domain:admin-tech',
            'office.dashboard' => 'domain:office',
            'procurement.dashboard' => 'domain:procurement',
            'inventory.dashboard' => 'domain:inventory',
            'executive.dashboard' => 'domain:executive',
        ];

        foreach ($routeNames as $routeName => $domainMiddleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertContains('auth', $route->middleware());
            $this->assertContains($domainMiddleware, $route->middleware());
        }

        $publicRoute = Route::getRoutes()->getByName('public.dashboard');

        $this->assertNotNull($publicRoute);
        $this->assertNotContains('auth', $publicRoute->middleware());
        $this->assertContains('can:public.disclosures.view', $publicRoute->middleware());

        $adminUsersRoute = Route::getRoutes()->getByName('admin-tech.users.index');
        $adminUsersEditRoute = Route::getRoutes()->getByName('admin-tech.users.edit');
        $adminUsersUpdateRoute = Route::getRoutes()->getByName('admin-tech.users.update');
        $adminUsersDestroyRoute = Route::getRoutes()->getByName('admin-tech.users.destroy');
        $adminOfficesRoute = Route::getRoutes()->getByName('admin-tech.offices.index');
        $adminOfficesEditRoute = Route::getRoutes()->getByName('admin-tech.offices.edit');
        $adminOfficesUpdateRoute = Route::getRoutes()->getByName('admin-tech.offices.update');
        $adminOfficesDestroyRoute = Route::getRoutes()->getByName('admin-tech.offices.destroy');
        $adminRolesRoute = Route::getRoutes()->getByName('admin-tech.roles.index');
        $executiveProcurementAnalyticsRoute = Route::getRoutes()->getByName('executive.procurement-analytics');
        $executiveAnomaliesMonitoringRoute = Route::getRoutes()->getByName('executive.anomalies-monitoring');
        $executiveReportsRoute = Route::getRoutes()->getByName('executive.reports');
        $executiveTransparencyMonitoringRoute = Route::getRoutes()->getByName('executive.transparency-monitoring');

        $this->assertNotNull($adminUsersRoute);
        $this->assertNotNull($adminUsersEditRoute);
        $this->assertNotNull($adminUsersUpdateRoute);
        $this->assertNotNull($adminUsersDestroyRoute);
        $this->assertNotNull($adminOfficesRoute);
        $this->assertNotNull($adminOfficesEditRoute);
        $this->assertNotNull($adminOfficesUpdateRoute);
        $this->assertNotNull($adminOfficesDestroyRoute);
        $this->assertNotNull($adminRolesRoute);
        $this->assertNotNull($executiveProcurementAnalyticsRoute);
        $this->assertNotNull($executiveAnomaliesMonitoringRoute);
        $this->assertNotNull($executiveReportsRoute);
        $this->assertNotNull($executiveTransparencyMonitoringRoute);

        $this->assertContains('domain:admin-tech', $adminUsersRoute->middleware());
        $this->assertContains('can:governance.manage-users', $adminUsersRoute->middleware());
        $this->assertContains('can:governance.manage-users', $adminUsersEditRoute->middleware());
        $this->assertContains('can:governance.manage-users', $adminUsersUpdateRoute->middleware());
        $this->assertContains('can:governance.manage-users', $adminUsersDestroyRoute->middleware());

        $this->assertContains('domain:admin-tech', $adminOfficesRoute->middleware());
        $this->assertContains('can:governance.manage-offices', $adminOfficesRoute->middleware());
        $this->assertContains('can:governance.manage-offices', $adminOfficesEditRoute->middleware());
        $this->assertContains('can:governance.manage-offices', $adminOfficesUpdateRoute->middleware());
        $this->assertContains('can:governance.manage-offices', $adminOfficesDestroyRoute->middleware());

        $this->assertContains('domain:admin-tech', $adminRolesRoute->middleware());
        $this->assertContains('can:governance.view-roles', $adminRolesRoute->middleware());

        $this->assertContains('domain:executive', $executiveProcurementAnalyticsRoute->middleware());
        $this->assertContains('can:executive.monitoring.view', $executiveProcurementAnalyticsRoute->middleware());

        $this->assertContains('domain:executive', $executiveAnomaliesMonitoringRoute->middleware());
        $this->assertContains('can:executive.monitoring.view', $executiveAnomaliesMonitoringRoute->middleware());

        $this->assertContains('domain:executive', $executiveReportsRoute->middleware());
        $this->assertContains('can:executive.monitoring.view', $executiveReportsRoute->middleware());

        $this->assertContains('domain:executive', $executiveTransparencyMonitoringRoute->middleware());
        $this->assertContains('can:executive.monitoring.view', $executiveTransparencyMonitoringRoute->middleware());
    }

    public function test_admin_tech_subpages_are_not_reachable_by_non_admin_roles(): void
    {
        $office = Office::query()->create([
            'name' => 'Main Office',
            'code' => 'MAIN_OFFICE',
            'description' => 'Main test office',
            'is_active' => true,
        ]);

        $admin = $this->createUser(UserRole::ADMIN, $office);
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $managedUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $this->actingAs($admin)->get('/admin-tech/users')->assertOk();
        $this->actingAs($admin)->get('/admin-tech/users/'.$managedUser->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin-tech/offices/'.$office->id.'/edit')->assertOk();
        $this->actingAs($officeUser)->get('/admin-tech/users')->assertForbidden();
        $this->actingAs($officeUser)->get('/admin-tech/users/'.$managedUser->id.'/edit')->assertForbidden();
        $this->actingAs($officeUser)->get('/admin-tech/offices')->assertForbidden();
        $this->actingAs($officeUser)->get('/admin-tech/offices/'.$office->id.'/edit')->assertForbidden();
        $this->actingAs($officeUser)->get('/admin-tech/roles')->assertForbidden();
    }

    public function test_audit_logs_support_technical_and_business_domain_separation(): void
    {
        $office = Office::query()->create([
            'name' => 'Main Office',
            'code' => 'MAIN_OFFICE',
            'description' => 'Main test office',
            'is_active' => true,
        ]);

        $admin = $this->createUser(UserRole::ADMIN, $office);
        $execOfficer = $this->createUser(UserRole::EXECUTIVE_OFFICER, $office);

        $auditService = app(AuditLogService::class);

        $auditService->log($admin, 'Governance', 'UPDATE_USER', User::class, $admin->id, [], 'TECHNICAL', $office->id);
        $auditService->log($execOfficer, 'Executive', 'VIEW_BUSINESS_AUDIT', User::class, $execOfficer->id, [], 'BUSINESS', $office->id);

        $this->assertTrue($admin->can('audit.technical.view'));
        $this->assertFalse($admin->can('audit.business.view'));
        $this->assertTrue($execOfficer->can('audit.business.view'));
        $this->assertFalse($execOfficer->can('audit.technical.view'));

        $this->assertSame(1, AuditLog::query()->technical()->count());
        $this->assertSame(1, AuditLog::query()->business()->count());
    }

    public function test_notification_structure_is_future_safe_for_workflow_events(): void
    {
        $office = Office::query()->create([
            'name' => 'Main Office',
            'code' => 'MAIN_OFFICE',
            'description' => 'Main test office',
            'is_active' => true,
        ]);

        $admin = $this->createUser(UserRole::ADMIN, $office);
        $receiver = $this->createUser(UserRole::OFFICE_USER, $office);

        $notificationService = app(NotificationService::class);

        $notification = $notificationService->notifyUser(
            user: $receiver,
            title: 'Workflow Placeholder',
            message: 'Foundation-only notification test.',
            notificationType: 'GOVERNANCE_EVENT',
            referenceType: User::class,
            referenceId: $admin->id,
        );

        $this->assertDatabaseHas('notifications', [
            'notification_id' => $notification->notification_id,
            'notification_type' => 'GOVERNANCE_EVENT',
            'reference_type' => User::class,
            'reference_id' => $admin->id,
            'is_read' => false,
        ]);

        $this->assertTrue(Schema::hasColumns('audit_logs', ['office_id', 'log_domain']));
        $this->assertTrue(Schema::hasColumns('notifications', [
            'notification_id',
            'notification_type',
            'reference_type',
            'reference_id',
            'is_read',
        ]));
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for hardening tests',
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
