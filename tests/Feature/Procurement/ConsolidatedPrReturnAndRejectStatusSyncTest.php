<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedPrReturnAndRejectStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_returning_consolidated_pr_for_revision_syncs_all_source_supply_requests_to_draft(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $office = Office::query()->create(['name' => 'MPDO', 'code' => 'MPDO', 'status' => 'ACTIVE']);

        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);

        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Test 1',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => 1,
            'item_name_snapshot' => 'Bond Paper',
            'quantity_requested' => 10,
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-0002',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Test 2',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => 1,
            'item_name_snapshot' => 'Bond Paper',
            'quantity_requested' => 20,
        ]);

        $prService = app(PurchaseRequestService::class);

        $pr = $prService->queueConsolidatedFromOffice(
            actor: $gsoUser,
            officeId: (int) $office->id,
            supplyRequestIds: [(int) $sr1->supply_request_id, (int) $sr2->supply_request_id],
        );

        $this->assertSame('FOR_PROCUREMENT', (string) $sr1->fresh()->status);
        $this->assertSame('FOR_PROCUREMENT', (string) $sr2->fresh()->status);

        // Procurement returns the consolidated PR for revision
        $prService->returnForRevision($pr, $procUser, 'Please revise requested item specs.');

        $this->assertSame('RETURNED_FOR_REVISION', (string) $pr->fresh()->status);
        $this->assertSame('DRAFT', (string) $sr1->fresh()->status);
        $this->assertSame('DRAFT', (string) $sr2->fresh()->status);
    }

    public function test_rejecting_consolidated_pr_syncs_all_source_supply_requests_to_rejected(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $office = Office::query()->create(['name' => 'MPDO', 'code' => 'MPDO', 'status' => 'ACTIVE']);

        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);

        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-0003',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Test 3',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => 1,
            'item_name_snapshot' => 'Bond Paper',
            'quantity_requested' => 10,
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-TEST-0004',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Test 4',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => 1,
            'item_name_snapshot' => 'Bond Paper',
            'quantity_requested' => 20,
        ]);

        $prService = app(PurchaseRequestService::class);

        $pr = $prService->queueConsolidatedFromOffice(
            actor: $gsoUser,
            officeId: (int) $office->id,
            supplyRequestIds: [(int) $sr1->supply_request_id, (int) $sr2->supply_request_id],
        );

        // Procurement rejects the consolidated PR
        $prService->reject($pr, $procUser, 'Consolidation rejected due to budget constraints.');

        $this->assertSame('REJECTED', (string) $pr->fresh()->status);
        $this->assertSame('REJECTED', (string) $sr1->fresh()->status);
        $this->assertSame('REJECTED', (string) $sr2->fresh()->status);
    }
}
