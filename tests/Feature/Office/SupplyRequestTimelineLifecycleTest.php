<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyRequestTimelineLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createOffice(string $code = 'MPDO', string $name = 'Municipal Planning'): Office
    {
        return Office::query()->create([
            'code' => $code,
            'name' => $name,
        ]);
    }

    private function createUser(UserRole $role, Office $office, string $name = 'Test User'): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => $role->value.' role',
                'is_active' => true,
            ]
        );

        return User::factory()->create([
            'name' => $name,
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'is_active' => true,
        ]);
    }

    public function test_completed_supply_request_marks_all_stages_completed_with_confirmed_by_and_no_active_stage(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office, 'Earl Requisitioner');

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-COMP-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->subDays(10)->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Completed lifecycle test',
            'priority_level' => 'NORMAL',
            'status' => 'COMPLETED',
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'COMPLETED',
            'remarks' => 'Fulfillment complete',
        ]);

        $response = $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Office/SupplyRequests/Show')
            ->where('supplyRequest.status', 'COMPLETED')
            ->where('supplyRequest.processing_timeline.workflow_status', 'COMPLETED')
            ->where('supplyRequest.processing_timeline.is_completed', true)
            ->where('supplyRequest.processing_timeline.is_rejected', false)
            ->where('supplyRequest.processing_timeline.stages.0.status_badge', 'COMPLETED')
            ->where('supplyRequest.processing_timeline.stages.0.is_completed', true)
            ->where('supplyRequest.processing_timeline.stages.0.is_current', false)
            ->where('supplyRequest.processing_timeline.stages.5.status_badge', 'COMPLETED')
            ->where('supplyRequest.processing_timeline.stages.5.is_completed', true)
            ->where('supplyRequest.processing_timeline.stages.5.is_current', false)
            ->where('supplyRequest.processing_timeline.stages.5.action_label', 'Confirmed by:')
        );
    }

    public function test_rejected_supply_request_at_procurement_stage_shows_rejected_and_discontinued_stages(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office, 'Earl Requisitioner');
        $bacOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office, 'BAC Procurement Officer');

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-REJ-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->subDays(5)->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Rejection lifecycle test',
            'priority_level' => 'NORMAL',
            'status' => 'REJECTED',
            'review_notes' => 'Rejected during procurement evaluation due to budget constraint.',
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $bacOfficer->id,
            'status' => 'REJECTED',
            'remarks' => 'Supplier pricing exceeds approved appropriation.',
        ]);

        $response = $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Office/SupplyRequests/Show')
            ->where('supplyRequest.status', 'REJECTED')
            ->where('supplyRequest.processing_timeline.workflow_status', 'REJECTED')
            ->where('supplyRequest.processing_timeline.is_rejected', true)
            ->where('supplyRequest.processing_timeline.is_completed', false)
            // Stage 1, 2, 3 completed
            ->where('supplyRequest.processing_timeline.stages.0.status_badge', 'COMPLETED')
            ->where('supplyRequest.processing_timeline.stages.1.status_badge', 'COMPLETED')
            ->where('supplyRequest.processing_timeline.stages.2.status_badge', 'COMPLETED')
            // Stage 4 is REJECTED
            ->where('supplyRequest.processing_timeline.stages.3.status_badge', 'REJECTED')
            ->where('supplyRequest.processing_timeline.stages.3.is_rejected', true)
            ->where('supplyRequest.processing_timeline.stages.3.is_current', false)
            ->where('supplyRequest.processing_timeline.stages.3.action_label', 'Rejected by:')
            // Stages 5 and 6 DISCONTINUED
            ->where('supplyRequest.processing_timeline.stages.4.status_badge', 'DISCONTINUED')
            ->where('supplyRequest.processing_timeline.stages.4.is_discontinued', true)
            ->where('supplyRequest.processing_timeline.stages.4.is_current', false)
            ->where('supplyRequest.processing_timeline.stages.5.status_badge', 'DISCONTINUED')
            ->where('supplyRequest.processing_timeline.stages.5.is_discontinued', true)
            ->where('supplyRequest.processing_timeline.stages.5.is_current', false)
        );
    }

    public function test_in_progress_supply_request_marks_active_stage_only_on_current_stage(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office, 'Earl Requisitioner');
        $procOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office, 'Proc Jeron');

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-INPROG-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(10)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'In progress lifecycle test',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procOfficer->id,
            'status' => 'PROCESSING',
            'remarks' => 'Under BAC evaluation',
        ]);

        $response = $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Office/SupplyRequests/Show')
            ->where('supplyRequest.status', 'PROCESSING')
            ->where('supplyRequest.processing_timeline.workflow_status', 'IN_PROGRESS')
            ->where('supplyRequest.processing_timeline.stages.3.status_badge', 'ACTIVE')
            ->where('supplyRequest.processing_timeline.stages.3.is_current', true)
            ->where('supplyRequest.processing_timeline.stages.3.action_label', 'Currently Handled By:')
            ->where('supplyRequest.processing_timeline.stages.4.status_badge', 'PENDING')
            ->where('supplyRequest.processing_timeline.stages.4.is_current', false)
            ->where('supplyRequest.processing_timeline.stages.5.status_badge', 'PENDING')
            ->where('supplyRequest.processing_timeline.stages.5.is_current', false)
        );
    }

    public function test_stage_4_procurement_officer_never_attributed_to_executive_officer(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office, 'Earl Requisitioner');
        $procOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office, 'Proc Jeron');
        $execUser = $this->createUser(UserRole::EXECUTIVE_OFFICER, $office, 'Mayor Jeron');

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-PO-ATTRIB-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->subDays(5)->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Executive attribution prevention test',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procOfficer->id,
            'status' => 'PROCESSING',
        ]);

        $supplier = \App\Models\Supplier::query()->create([
            'supplier_name' => 'Metro Depot',
            'contact_person' => 'Supplier Rep',
            'email' => 'supplier@example.com',
            'phone' => '123456789',
            'status' => 'ACTIVE',
        ]);

        PurchaseOrder::query()->create([
            'po_number' => 'PO-ATTRIB-001',
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procOfficer->id,
            'approved_by' => $execUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'COMPLETED',
            'expected_delivery_date' => now()->toDateString(),
            'total_amount' => 5000,
        ]);

        $response = $this->actingAs($officeUser)
            ->get(route('office.supply-requests.show', $supplyRequest->supply_request_id))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Office/SupplyRequests/Show')
            // Assert that stage 4 actor is the Procurement Officer, NOT the Executive (Mayor)
            ->where('supplyRequest.processing_timeline.stages.3.actor_name', 'Proc Jeron')
            ->whereNot('supplyRequest.processing_timeline.stages.3.actor_name', 'Mayor Jeron')
        );
    }
}
