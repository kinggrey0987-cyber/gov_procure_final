<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\Role;
use App\Models\StatusHistory;
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
    protected User $gsoOfficer;
    protected User $procurementOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = $this->createOffice('Executive Approval Office', 'EAO');
        $this->executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
        $this->admin = $this->createUser(UserRole::ADMIN, $this->office);
        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office);
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

        $this->actingAs($this->gsoOfficer)
            ->get(route('executive.ppmp-approvals.index'))
            ->assertForbidden();

        $this->actingAs($this->procurementOfficer)
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

    public function test_executive_can_return_ppmp_with_required_remarks(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => 'Please revise the quantity assumptions and procurement schedule.',
            ])
            ->assertRedirect(route('executive.ppmp-approvals.show', $ppmp->ppmp_id));

        $ppmp->refresh();

        $this->assertSame('RETURNED_FOR_REVISION', $ppmp->status);
        $this->assertDatabaseHas('status_history', [
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'old_status' => 'SUBMITTED',
            'new_status' => 'RETURNED_FOR_REVISION',
            'remarks' => 'Please revise the quantity assumptions and procurement schedule.',
        ]);
        $this->assertDatabaseHas('approval_history', [
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'status' => 'RETURNED_FOR_REVISION',
            'approved_by' => $this->executive->id,
            'remarks' => 'Please revise the quantity assumptions and procurement schedule.',
        ]);
    }

    public function test_executive_cannot_return_ppmp_without_remarks(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => '',
            ])
            ->assertSessionHasErrors(['remarks']);

        $ppmp->refresh();
        $this->assertSame('SUBMITTED', $ppmp->status);
    }

    public function test_executive_can_reject_ppmp_with_required_remarks(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'REJECTED',
                'remarks' => 'Rejected due to unsupported quantities and missing justification.',
            ])
            ->assertRedirect(route('executive.ppmp-approvals.show', $ppmp->ppmp_id));

        $ppmp->refresh();

        $this->assertSame('REJECTED', $ppmp->status);
        $this->assertDatabaseHas('approval_history', [
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'status' => 'REJECTED',
            'approved_by' => $this->executive->id,
            'remarks' => 'Rejected due to unsupported quantities and missing justification.',
        ]);
    }

    public function test_executive_cannot_reject_ppmp_without_remarks(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->actingAs($this->executive)
            ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                'status' => 'REJECTED',
                'remarks' => '   ',
            ])
            ->assertSessionHasErrors(['remarks']);

        $ppmp->refresh();
        $this->assertSame('SUBMITTED', $ppmp->status);
    }

    public function test_non_executive_roles_cannot_signoff_ppmp(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        foreach ([$this->officeUser, $this->admin, $this->gsoOfficer, $this->procurementOfficer] as $actor) {
            $this->actingAs($actor)
                ->put(route('executive.ppmp-approvals.update-status', $ppmp->ppmp_id), [
                    'status' => 'APPROVED',
                ])
                ->assertForbidden();
        }

        $ppmp->refresh();
        $this->assertSame('SUBMITTED', $ppmp->status);
    }

    public function test_executive_index_exposes_can_signoff_flag_aligned_with_backend_gate(): void
    {
        $submitted = $this->createSubmittedPpmp();

        $approved = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'approved_by' => $this->executive->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'total_estimated_budget' => 90000.00,
            'date_prepared' => now()->subDays(7)->toDateString(),
            'date_approved' => now()->subDays(1)->toDateString(),
            'remarks' => 'Already approved.',
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.ppmp-approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Approvals/Index')
                ->where('records.data.0.ppmp_id', $approved->ppmp_id)
                ->where('records.data.0.can_signoff', false)
                ->where('records.data.1.ppmp_id', $submitted->ppmp_id)
                ->where('records.data.1.can_signoff', true)
            );
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

    public function test_executive_show_exposes_only_significant_workflow_timeline_entries(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        StatusHistory::query()->create([
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'old_status' => null,
            'new_status' => 'DRAFT',
            'changed_by' => $this->officeUser->id,
            'remarks' => 'PPMP draft initialized.',
            'changed_at' => now()->subDays(3),
        ]);

        StatusHistory::query()->create([
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'old_status' => 'DRAFT',
            'new_status' => 'DRAFT',
            'changed_by' => $this->officeUser->id,
            'remarks' => 'Line item revised.',
            'changed_at' => now()->subDays(2),
        ]);

        StatusHistory::query()->create([
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'old_status' => 'DRAFT',
            'new_status' => 'SUBMITTED',
            'changed_by' => $this->officeUser->id,
            'remarks' => 'Submitted to executive.',
            'changed_at' => now()->subDay(),
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.ppmp-approvals.show', $ppmp->ppmp_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Approvals/Show')
                ->has('workflowTimeline', 2)
                ->where('workflowTimeline.0.new_status', 'SUBMITTED')
                ->where('workflowTimeline.1.new_status', 'DRAFT')
            );
    }

    public function test_executive_show_falls_back_to_ppmp_created_date_when_submission_history_is_missing(): void
    {
        $ppmp = $this->createSubmittedPpmp();

        $this->assertDatabaseMissing('status_history', [
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmp->ppmp_id,
            'new_status' => 'SUBMITTED',
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.ppmp-approvals.show', $ppmp->ppmp_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Approvals/Show')
                ->where('ppmp.submitted_at', $ppmp->created_at->toIso8601String())
            );
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
            'emp_id' => strtoupper(substr($role->value, 0, 4)).'-'.strtoupper(substr(uniqid('', true), -8)),
            'password' => Hash::make('password'),
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'is_active' => true,
        ]);
    }
}
