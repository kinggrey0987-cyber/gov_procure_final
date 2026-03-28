<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected Office $technicalOffice;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Admin HQ',
            'code' => 'ADMIN_HQ',
            'description' => 'Admin office',
            'is_active' => true,
        ]);

        $this->technicalOffice = Office::query()->create([
            'name' => 'Technical Administration',
            'code' => 'TECH_ADMIN',
            'description' => 'Technical office',
            'is_active' => true,
        ]);

        $this->admin = $this->createUser(UserRole::ADMIN, $this->technicalOffice, 'admin@gsc.local');

        foreach (UserRole::cases() as $role) {
            $this->ensureRole($role);
        }
    }

    public function test_admin_can_create_edit_and_delete_or_deactivate_office_safely(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin-tech.offices.store'), [
                'name' => 'City Engineering Office',
                'code' => 'ENG_NEW',
                'description' => 'Engineering office',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin-tech.offices.index'));

        $created = Office::query()->where('code', 'ENG_NEW')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin-tech.offices.update', $created->id), [
                'name' => 'City Engineering Services',
                'code' => 'ENG_NEW',
                'description' => 'Updated',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin-tech.offices.index'));

        $created->refresh();
        $this->assertSame('City Engineering Services', $created->name);
        $this->assertFalse($created->is_active);

        $linkedOffice = Office::query()->create([
            'name' => 'Linked Office',
            'code' => 'LINKED_OFF',
            'description' => null,
            'is_active' => true,
        ]);

        $this->createUser(UserRole::OFFICE_USER, $linkedOffice, 'linked.user@gsc.local');

        $this->actingAs($this->admin)
            ->delete(route('admin-tech.offices.destroy', $linkedOffice->id))
            ->assertRedirect(route('admin-tech.offices.index'));

        $this->assertDatabaseHas('offices', [
            'id' => $linkedOffice->id,
            'is_active' => false,
        ]);

        $deletableOffice = Office::query()->create([
            'name' => 'Temporary Office',
            'code' => 'TEMP_OFF',
            'description' => null,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin-tech.offices.destroy', $deletableOffice->id))
            ->assertRedirect(route('admin-tech.offices.index'));

        $this->assertDatabaseMissing('offices', [
            'id' => $deletableOffice->id,
        ]);
    }

    public function test_admin_can_create_edit_and_delete_internal_user(): void
    {
        $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);
        $roleProc = $this->roleByCode(UserRole::PROCUREMENT_OFFICER->value);

        $targetOffice = Office::query()->create([
            'name' => 'BAC Office',
            'code' => 'BAC_MAIN',
            'description' => null,
            'is_active' => true,
        ]);

        $otherOffice = Office::query()->create([
            'name' => 'GSO Office',
            'code' => 'GSO_MAIN',
            'description' => null,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin-tech.users.store'), [
                'name' => 'Internal Staff',
                'email' => 'staff@gsc.local',
                'password' => 'TempPass#12345',
                'password_confirmation' => 'TempPass#12345',
                'role_id' => $roleOfficeUser->id,
                'office_id' => $targetOffice->id,
            ])
            ->assertRedirect(route('admin-tech.users.index'));

        $user = User::query()->where('email', 'staff@gsc.local')->firstOrFail();

        $this->assertTrue(password_verify('TempPass#12345', $user->password));
        $this->assertTrue($user->must_change_password);

        $this->actingAs($this->admin)
            ->put(route('admin-tech.users.update', $user->id), [
                'name' => 'Internal Staff Updated',
                'email' => 'staff.updated@gsc.local',
                'role_id' => $roleProc->id,
                'office_id' => $otherOffice->id,
            ])
            ->assertRedirect(route('admin-tech.users.index'));

        $user->refresh();
        $this->assertSame('Internal Staff Updated', $user->name);
        $this->assertSame('staff.updated@gsc.local', $user->email);
        $this->assertSame($roleProc->id, $user->role_id);
        $this->assertSame($otherOffice->id, $user->office_id);

        $this->actingAs($this->admin)
            ->delete(route('admin-tech.users.destroy', $user->id))
            ->assertRedirect(route('admin-tech.users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_non_admin_users_are_forbidden_from_admin_management_actions(): void
    {
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office, 'office@gsc.local');

        $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);
        $managedUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office, 'managed@gsc.local');

        $this->actingAs($officeUser)->get(route('admin-tech.users.create'))->assertForbidden();
        $this->actingAs($officeUser)->post(route('admin-tech.users.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked@gsc.local',
            'password' => 'TempPass#12345',
            'password_confirmation' => 'TempPass#12345',
            'role_id' => $roleOfficeUser->id,
            'office_id' => $this->office->id,
        ])->assertForbidden();

        $this->actingAs($officeUser)->put(route('admin-tech.users.update', $managedUser->id), [
            'name' => 'Blocked Update',
            'email' => 'blocked.update@gsc.local',
            'role_id' => $roleOfficeUser->id,
            'office_id' => $this->office->id,
        ])->assertForbidden();

        $this->actingAs($officeUser)->delete(route('admin-tech.users.destroy', $managedUser->id))->assertForbidden();

        $this->actingAs($officeUser)->get(route('admin-tech.offices.create'))->assertForbidden();
        $this->actingAs($officeUser)->post(route('admin-tech.offices.store'), [
            'name' => 'Blocked Office',
            'code' => 'BLOCKED',
            'description' => null,
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($officeUser)->put(route('admin-tech.offices.update', $this->office->id), [
            'name' => 'Blocked Update',
            'code' => 'ADMIN_HQ',
            'description' => null,
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($officeUser)->delete(route('admin-tech.offices.destroy', $this->office->id))->assertForbidden();
    }

    public function test_public_role_is_excluded_from_internal_user_management(): void
    {
        $publicRole = $this->roleByCode(UserRole::PUBLIC->value);

        $this->actingAs($this->admin)
            ->get(route('admin-tech.users.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminTech/Users/Create')
                ->where('roles', fn ($roles) => collect($roles)->every(fn ($role) => $role['code'] !== UserRole::PUBLIC->value))
            );

        $this->actingAs($this->admin)
            ->post(route('admin-tech.users.store'), [
                'name' => 'Public Attempt',
                'email' => 'public.attempt@gsc.local',
                'password' => 'TempPass#12345',
                'password_confirmation' => 'TempPass#12345',
                'role_id' => $publicRole->id,
                'office_id' => $this->office->id,
            ])
            ->assertSessionHasErrors('role_id');
    }

    public function test_admin_creation_uses_technical_office_and_rejects_operational_office_selection(): void
    {
        $adminRole = $this->roleByCode(UserRole::ADMIN->value);

        $this->actingAs($this->admin)
            ->post(route('admin-tech.users.store'), [
                'name' => 'Admin Attempt',
                'email' => 'admin.attempt@gsc.local',
                'password' => 'TempPass#12345',
                'password_confirmation' => 'TempPass#12345',
                'role_id' => $adminRole->id,
                'office_id' => $this->office->id,
            ])
            ->assertSessionHasErrors('office_id');

        $this->actingAs($this->admin)
            ->post(route('admin-tech.users.store'), [
                'name' => 'Admin Technical User',
                'email' => 'admin.tech@gsc.local',
                'password' => 'TempPass#12345',
                'password_confirmation' => 'TempPass#12345',
                'role_id' => $adminRole->id,
            ])
            ->assertRedirect(route('admin-tech.users.index'));

        $adminUser = User::query()->where('email', 'admin.tech@gsc.local')->firstOrFail();

        $this->assertSame($this->technicalOffice->id, $adminUser->office_id);
        $this->assertTrue($adminUser->must_change_password);
    }

    public function test_non_admin_roles_require_operational_office_and_reject_technical_office(): void
    {
        $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);

        $this->actingAs($this->admin)
            ->post(route('admin-tech.users.store'), [
                'name' => 'Missing Office',
                'email' => 'missing.office@gsc.local',
                'password' => 'TempPass#12345',
                'password_confirmation' => 'TempPass#12345',
                'role_id' => $roleOfficeUser->id,
            ])
            ->assertSessionHasErrors('office_id');

        $this->actingAs($this->admin)
            ->post(route('admin-tech.users.store'), [
                'name' => 'Wrong Office',
                'email' => 'wrong.office@gsc.local',
                'password' => 'TempPass#12345',
                'password_confirmation' => 'TempPass#12345',
                'role_id' => $roleOfficeUser->id,
                'office_id' => $this->technicalOffice->id,
            ])
            ->assertSessionHasErrors('office_id');
    }

    public function test_edit_flow_enforces_admin_and_non_admin_office_rules(): void
    {
        $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);
        $roleProcurement = $this->roleByCode(UserRole::PROCUREMENT_OFFICER->value);
        $roleAdmin = $this->roleByCode(UserRole::ADMIN->value);

        $staff = $this->createUser(UserRole::OFFICE_USER, $this->office, 'role.switch@gsc.local');

        $this->actingAs($this->admin)
            ->put(route('admin-tech.users.update', $staff->id), [
                'name' => 'Role Switch User',
                'email' => 'role.switch@gsc.local',
                'role_id' => $roleAdmin->id,
                'office_id' => $this->office->id,
            ])
            ->assertSessionHasErrors('office_id');

        $this->actingAs($this->admin)
            ->put(route('admin-tech.users.update', $staff->id), [
                'name' => 'Role Switch User',
                'email' => 'role.switch@gsc.local',
                'role_id' => $roleAdmin->id,
            ])
            ->assertRedirect(route('admin-tech.users.index'));

        $staff->refresh();
        $this->assertSame($this->technicalOffice->id, $staff->office_id);

        $this->actingAs($this->admin)
            ->put(route('admin-tech.users.update', $staff->id), [
                'name' => 'Role Switch User',
                'email' => 'role.switch@gsc.local',
                'role_id' => $roleProcurement->id,
            ])
            ->assertSessionHasErrors('office_id');

        $this->actingAs($this->admin)
            ->put(route('admin-tech.users.update', $staff->id), [
                'name' => 'Role Switch User Updated',
                'email' => 'role.switch.updated@gsc.local',
                'role_id' => $roleOfficeUser->id,
                'office_id' => $this->office->id,
            ])
            ->assertRedirect(route('admin-tech.users.index'));

        $staff->refresh();
        $this->assertSame($this->office->id, $staff->office_id);
    }

    public function test_self_delete_and_linked_record_delete_protections_work(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin-tech.users.destroy', $this->admin->id))
            ->assertSessionHasErrors('delete');

        $linkedUser = $this->createUser(UserRole::OFFICE_USER, $this->office, 'linked.record@gsc.local');

        Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $linkedUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'DRAFT',
            'total_estimated_budget' => 0,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => 'link check',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin-tech.users.destroy', $linkedUser->id))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', [
            'id' => $linkedUser->id,
        ]);
    }

    private function createUser(UserRole $role, Office $office, string $email): User
    {
        $roleModel = $this->ensureRole($role);

        return User::factory()
            ->withRole($role)
            ->forOffice($office)
            ->create([
                'email' => $email,
                'role_id' => $roleModel->id,
                'office_id' => $office->id,
            ]);
    }

    private function ensureRole(UserRole $role): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for admin CRUD tests',
                'is_active' => true,
            ],
        );
    }

    private function roleByCode(string $code): Role
    {
        return Role::query()->where('code', $code)->firstOrFail();
    }
}
