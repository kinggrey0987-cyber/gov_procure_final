<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExecutivePpmpApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected User $executive;
    protected User $officeUser;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = $this->createOffice('Executive Approval Office', 'EAO');
        $this->executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
        $this->admin = $this->createUser(UserRole::ADMIN, $this->office);
    }

    public function test_exec_officer_can_access_executive_ppmp_approval_routes_only(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->get(route('executive.ppmp-approvals.index'))
            ->assertOk();

        $this->actingAs($this->executive)
            ->get(route('executive.ppmp-approvals.show', $ppmp->ppmp_id))
            ->assertOk();

        $this->actingAs($this->officeUser)
            ->get(route('executive.ppmp-approvals.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('executive.ppmp-approvals.index'))
            ->assertForbidden();
    }

    public function test_executive_approval_writes_status_approval_and_audit_history(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'APPROVED',
                'remarks' => 'Executive signoff granted.',
            ])
            ->assertRedirect();

        $ppmp->refresh();

        $this->assertSame('APPROVED', $ppmp->status);
        $this->assertSame($this->executive->id, $ppmp->approved_by);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'old_status' => 'SUBMITTED',
            'new_status' => 'APPROVED',
        ]);

        $this->assertDatabaseHas('approval_history', [
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'approved_by' => $this->executive->id,
            'status' => 'APPROVED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module_name' => 'Executive.PPMP',
            'action_type' => 'SIGNOFF',
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'log_domain' => 'BUSINESS',
        ]);
    }

    public function test_return_for_revision_sets_status_and_allows_office_editing_again(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => 'Please revise item assumptions.',
            ])
            ->assertRedirect();

        $ppmp->refresh();

        $this->assertSame('RETURNED_FOR_REVISION', $ppmp->status);
        $this->assertTrue($this->officeUser->can('office.ppmp.update', $ppmp));
        $this->assertTrue($this->officeUser->can('office.ppmp.submit', $ppmp));
    }

    private function createSubmittedPpmp(): Ppmp
    {
        return Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 120000.00,
            'date_prepared' => now()->subDays(5)->toDateString(),
            'date_approved' => null,
            'remarks' => 'Submitted for executive signoff.',
        ]);
    }

    private function createOffice(string $name, string $code): Office
    {
        return Office::query()->create([
            'name' => $name,
            'code' => $code,
            'description' => $name.' office',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => $role->value.' role for tests',
                'is_active' => true,
            ]
        );

        return User::query()->create([
            'name' => strtolower($role->value).' user',
            'email' => strtolower($role->value).'.'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'is_active' => true,
        ]);
    }
}
