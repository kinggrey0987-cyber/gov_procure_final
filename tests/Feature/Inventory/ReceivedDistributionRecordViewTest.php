<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivedDistributionRecordViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_gso_and_office_can_view_received_distribution_record_with_claim_follow_up(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);

        $office = Office::query()->create(['name' => 'Assessor Office', 'code' => 'ASSO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Office Requester']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Custodian']);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Ballpen, Blue',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 1000.00,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 100.00,
            'estimated_budget' => 1000.00,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-ASSO-2026-001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'RECEIVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office writing supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'ppmp_id' => $ppmp->ppmp_id,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen, Blue',
            'quantity_requested' => 5,
        ]);

        $dist = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-999',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'received_by' => $officeUser->id,
        ]);

        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen, Blue',
            'quantity_released' => 5,
            'quantity_received' => 5,
            'unit' => 'box',
        ]);

        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-999',
            'distribution_record_id' => $dist->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $office->id,
            'claimed_by' => $officeUser->id,
            'claim_type' => 'DAMAGED_ITEM',
            'claim_location' => 'Office Storage',
            'quantity_disputed' => 5,
            'status' => 'PENDING_REPLACEMENT',
            'resolution_type' => 'PENDING_REPLACEMENT',
            'replacement_quantity' => 0,
            'claim_date' => now()->toDateString(),
            'remarks' => 'Leaking ink cartridges',
        ]);

        // Forward claim to follow-up PR
        $prService = app(PurchaseRequestService::class);
        $claimPr = $prService->createFromPendingClaimReplacement($claim, $gsoUser);

        $this->assertInstanceOf(PurchaseRequest::class, $claimPr);
        $this->assertSame('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $claimPr->source_type);

        // 1. GSO views distribution record show page
        $gsoResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.show', $dist->distribution_record_id));
        $gsoResponse->assertOk();
        $gsoResponse->assertInertia(fn ($page) => $page
            ->component('Inventory/Distribution/Show')
            ->where('record.distribution_record_id', (int) $dist->distribution_record_id)
            ->where('record.status', 'RECEIVED')
            ->where('record.is_claim_expired', false)
        );

        // 2. Office user views distribution record show page
        $officeDistResponse = $this->actingAs($officeUser)->get(route('office.distribution-records.show', $dist->distribution_record_id));
        $officeDistResponse->assertOk();

        // 3. Office user views supply request show page
        $officeSrResponse = $this->actingAs($officeUser)->get(route('office.supply-requests.show', $sr->supply_request_id));
        $officeSrResponse->assertOk();

        // 4. Inventory office supply requests index loads without relation errors
        $inventorySrIndex = $this->actingAs($gsoUser)->get(route('inventory.office-supply-requests.index'));
        $inventorySrIndex->assertOk();

        // 5. Check claim relationship works via hasOneThrough
        $claim->refresh();
        $this->assertNotNull($claim->supplyRequest);
        $this->assertSame((int) $sr->supply_request_id, (int) $claim->supplyRequest->supply_request_id);
    }
}
