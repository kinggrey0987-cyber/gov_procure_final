<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

        public function test_admin_can_create_user_with_emp_id(): void
        {
            $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);

            $this->actingAs($this->admin)
                ->post(route('admin-tech.users.store'), [
                    'name' => 'Employee ID User',
                    'email' => 'emp.id.user@gsc.local',
                    'emp_id' => 'EMP2026001',
                    'password' => 'TempPass#12345',
                    'password_confirmation' => 'TempPass#12345',
                    'role_id' => $roleOfficeUser->id,
                    'office_id' => $this->office->id,
                ])
                ->assertRedirect(route('admin-tech.users.index'));

            $user = User::query()->where('email', 'emp.id.user@gsc.local')->firstOrFail();
            $this->assertSame('EMP2026001', $user->emp_id);
        }

        public function test_admin_can_edit_user_emp_id(): void
        {
            $user = $this->createUser(UserRole::OFFICE_USER, $this->office, 'edit.emp@gsc.local');

            $this->actingAs($this->admin)
                ->put(route('admin-tech.users.update', $user->id), [
                    'name' => $user->name,
                    'email' => $user->email,
                    'emp_id' => 'EMP2026002',
                    'role_id' => $user->role_id,
                    'office_id' => $user->office_id,
                ])
                ->assertRedirect(route('admin-tech.users.index'));

            $user->refresh();
            $this->assertSame('EMP2026002', $user->emp_id);
        }

        public function test_duplicate_emp_id_is_rejected(): void
        {
            $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);

            // Create first user with emp_id
            User::factory()
                ->withRole(UserRole::OFFICE_USER)
                ->forOffice($this->office)
                ->create([
                    'email' => 'first.emp@gsc.local',
                    'emp_id' => 'EMP2026003',
                    'role_id' => $roleOfficeUser->id,
                    'office_id' => $this->office->id,
                ]);

            // Try to create second user with same emp_id
            $this->actingAs($this->admin)
                ->post(route('admin-tech.users.store'), [
                    'name' => 'Duplicate Emp ID',
                    'email' => 'second.emp@gsc.local',
                    'emp_id' => 'EMP2026003',
                    'password' => 'TempPass#12345',
                    'password_confirmation' => 'TempPass#12345',
                    'role_id' => $roleOfficeUser->id,
                    'office_id' => $this->office->id,
                ])
                ->assertSessionHasErrors('emp_id');
        }

        public function test_emp_id_update_rejects_duplicate_for_different_user(): void
        {
            $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);

            $user1 = $this->createUser(UserRole::OFFICE_USER, $this->office, 'user.one@gsc.local');
            $user2 = $this->createUser(UserRole::OFFICE_USER, $this->office, 'user.two@gsc.local');

            // Assign emp_id to user1
            $user1->update(['emp_id' => 'EMP2026004']);

            // Try to assign same emp_id to user2 (should fail)
            $this->actingAs($this->admin)
                ->put(route('admin-tech.users.update', $user2->id), [
                    'name' => $user2->name,
                    'email' => $user2->email,
                    'emp_id' => 'EMP2026004',
                    'role_id' => $user2->role_id,
                    'office_id' => $user2->office_id,
                ])
                ->assertSessionHasErrors('emp_id');
        }

        public function test_existing_user_without_emp_id_still_works(): void
        {
            // Create user without emp_id
            $user = $this->createUser(UserRole::OFFICE_USER, $this->office, 'no.emp.id@gsc.local');
            $this->assertNull($user->emp_id);

            // Can update the user (other fields) without specifying emp_id
            $this->actingAs($this->admin)
                ->put(route('admin-tech.users.update', $user->id), [
                    'name' => 'Updated Without Emp ID',
                    'email' => 'no.emp.id@gsc.local',
                    'role_id' => $user->role_id,
                    'office_id' => $user->office_id,
                ])
                ->assertRedirect(route('admin-tech.users.index'));

            $user->refresh();
            $this->assertSame('Updated Without Emp ID', $user->name);
            $this->assertNull($user->emp_id);
        }

        public function test_multiple_null_emp_id_values_are_allowed(): void
        {
            $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);

            // Create two users without emp_id (both will have null)
            $user1 = User::factory()
                ->withRole(UserRole::OFFICE_USER)
                ->forOffice($this->office)
                ->create([
                    'email' => 'null.emp.one@gsc.local',
                    'emp_id' => null,
                    'role_id' => $roleOfficeUser->id,
                    'office_id' => $this->office->id,
                ]);

            $user2 = User::factory()
                ->withRole(UserRole::OFFICE_USER)
                ->forOffice($this->office)
                ->create([
                    'email' => 'null.emp.two@gsc.local',
                    'emp_id' => null,
                    'role_id' => $roleOfficeUser->id,
                    'office_id' => $this->office->id,
                ]);

            // Both should exist and be queryable
            $this->assertDatabaseHas('users', ['id' => $user1->id, 'emp_id' => null]);
            $this->assertDatabaseHas('users', ['id' => $user2->id, 'emp_id' => null]);
        }

        public function test_emp_id_can_be_cleared_after_being_set(): void
        {
            $user = $this->createUser(UserRole::OFFICE_USER, $this->office, 'clear.emp@gsc.local');

            // Assign emp_id
            $user->update(['emp_id' => 'EMP2026005']);
            $this->assertSame('EMP2026005', $user->emp_id);

            // Clear emp_id via update
            $this->actingAs($this->admin)
                ->put(route('admin-tech.users.update', $user->id), [
                    'name' => $user->name,
                    'email' => $user->email,
                    'emp_id' => '',
                    'role_id' => $user->role_id,
                    'office_id' => $user->office_id,
                ])
                ->assertRedirect(route('admin-tech.users.index'));

            $user->refresh();
            $this->assertNull($user->emp_id);
        }

        public function test_emp_id_migration_is_safe_for_populated_users_table(): void
        {
            /** @var object{up: callable, down: callable} $migration */
            $migration = require database_path('migrations/2026_04_16_000100_add_emp_id_to_users_table.php');

            // Simulate pre-phase-1 state.
            $migration->down();

            $userA = $this->createUser(UserRole::OFFICE_USER, $this->office, 'migration.a@gsc.local');
            $userB = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office, 'migration.b@gsc.local');
            $beforeCount = User::query()->count();

            // Apply phase-1 migration on a populated users table.
            $migration->up();

            $this->assertTrue(Schema::hasColumn('users', 'emp_id'));
            $this->assertSame($beforeCount, User::query()->count());

            $userA->refresh();
            $userB->refresh();

            $this->assertNull($userA->emp_id);
            $this->assertNull($userB->emp_id);
        }

        public function test_phase_2_emp_id_is_required_on_create(): void
        {
            $roleOfficeUser = $this->roleByCode(UserRole::OFFICE_USER->value);

            // Try to create user without emp_id
            $this->actingAs($this->admin)
                ->post(route('admin-tech.users.store'), [
                    'name' => 'Missing Emp ID',
                    'email' => 'missing.emp.id@gsc.local',
                    'password' => 'TempPass#12345',
                    'password_confirmation' => 'TempPass#12345',
                    'role_id' => $roleOfficeUser->id,
                    'office_id' => $this->office->id,
                ])
                ->assertSessionHasErrors('emp_id');
        }

        public function test_phase_2_emp_id_is_required_on_update(): void
        {
            $user = $this->createUser(UserRole::OFFICE_USER, $this->office, 'update.emp@gsc.local');
            $user->update(['emp_id' => 'EMP2026099']);

            // Try to update user by clearing emp_id
            $this->actingAs($this->admin)
                ->put(route('admin-tech.users.update', $user->id), [
                    'name' => 'Updated Name',
                    'email' => $user->email,
                    'emp_id' => '',
                    'role_id' => $user->role_id,
                    'office_id' => $user->office_id,
                ])
                ->assertSessionHasErrors('emp_id');

            $user->refresh();
            $this->assertSame('EMP2026099', $user->emp_id);
        }

        public function test_phase_2_migration_enforces_not_null_and_unique(): void
        {
            /** @var object{up: callable, down: callable} $phase1Migration */
            $phase1Migration = require database_path('migrations/2026_04_16_000100_add_emp_id_to_users_table.php');

            /** @var object{up: callable, down: callable} $phase2Migration */
            $phase2Migration = require database_path('migrations/2026_04_16_000200_finalize_emp_id_not_null_and_unique.php');

            // Start from Phase 1 state
            $phase1Migration->down();
            $phase1Migration->up();

            // Populate with real emp_ids (simulating production state after backfill)
            $userA = $this->createUser(UserRole::OFFICE_USER, $this->office, 'phase2.a@gsc.local');
            $userB = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office, 'phase2.b@gsc.local');
            $userA->update(['emp_id' => 'EMP2026100']);
            $userB->update(['emp_id' => 'EMP2026101']);

            // Apply Phase 2 migration
            $phase2Migration->up();

            $this->assertTrue(Schema::hasColumn('users', 'emp_id'));
            $userA->refresh();
            $userB->refresh();
            $this->assertSame('EMP2026100', $userA->emp_id);
            $this->assertSame('EMP2026101', $userB->emp_id);

            // Verify we cannot add duplicate emp_id
            $this->expectException(\Exception::class);
            User::factory()
                ->withRole(UserRole::OFFICE_USER)
                ->forOffice($this->office)
                ->create([
                    'email' => 'duplicate.emp@gsc.local',
                    'emp_id' => 'EMP2026100',
                    'role_id' => $this->roleByCode(UserRole::OFFICE_USER->value)->id,
                    'office_id' => $this->office->id,
                ]);
        }
}

