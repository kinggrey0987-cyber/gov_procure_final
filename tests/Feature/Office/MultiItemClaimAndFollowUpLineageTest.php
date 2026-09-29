<?php

namespace Tests\Feature\Office;

use App\Models\Claim;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Item;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiItemClaimAndFollowUpLineageTest extends TestCase
{
    use RefreshDatabase;

    private User $officeUser;
    private User $gsoUser;
    private Office $office;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $roleOffice = Role::query()->firstOrCreate(['code' => \App\Enums\UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => \App\Enums\UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);

        $this->office = Office::create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $gsoOffice = Office::create(['name' => 'General Services Office', 'code' => 'GSO', 'status' => 'ACTIVE']);

        $this->officeUser = User::factory()->create([
            'role_id' => $roleOffice->id,
            'office_id' => $this->office->id,
            'is_active' => true,
        ]);

        $this->gsoUser = User::factory()->create([
            'role_id' => $roleGso->id,
            'office_id' => $gsoOffice->id,
            'is_active' => true,
        ]);
    }

    public function test_multi_item_claim_submission_creates_claims_per_item_without_overclaim_error(): void
    {
        $category = \App\Models\ItemCategory::query()->create(['category_name' => 'Office Supplies', 'status' => 'active']);
        $itemA = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Printer Ink, Black',
            'default_unit' => 'box',
            'status' => 'active',
        ]);
        $itemB = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Flash Drive, 32GB',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        $sr = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0007',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office operational supplies',
            'status' => 'RECEIVED',
        ]);

        $distRecord = DistributionRecord::create([
            'distribution_code' => 'DIST-2026-09-0007',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'notes' => 'Received goods.',
        ]);

        $distItemA = DistributionItem::create([
            'distribution_record_id' => $distRecord->distribution_record_id,
            'item_id' => $itemA->item_id,
            'item_name_snapshot' => $itemA->item_name,
            'quantity_requested' => 200,
            'quantity_released' => 200,
            'quantity_received' => 200,
            'unit' => 'box',
        ]);

        $distItemB = DistributionItem::create([
            'distribution_record_id' => $distRecord->distribution_record_id,
            'item_id' => $itemB->item_id,
            'item_name_snapshot' => $itemB->item_name,
            'quantity_requested' => 200,
            'quantity_released' => 200,
            'quantity_received' => 200,
            'unit' => 'box',
        ]);

        $evidenceFile = UploadedFile::fake()->create('defect_evidence.jpg', 500, 'image/jpeg');

        // Post multi-item claim: 200 damaged for Item A, 200 missing for Item B (total = 400 across items)
        $response = $this->actingAs($this->officeUser)->post(route('office.claims.store'), [
            'distribution_record_id' => $distRecord->distribution_record_id,
            'office_id' => $this->office->id,
            'claim_type' => 'MISSING_AND_DAMAGED',
            'claim_date' => now()->toDateString(),
            'claim_location' => 'Receiving area',
            'remarks' => 'Defects observed during inspection.',
            'attachment_file' => $evidenceFile,
            'items' => [
                [
                    'distribution_item_id' => $distItemA->distribution_item_id,
                    'claim_type' => 'DAMAGED_ITEM',
                    'quantity_disputed' => 200,
                    'quantity_damaged' => 200,
                    'quantity_missing' => 0,
                ],
                [
                    'distribution_item_id' => $distItemB->distribution_item_id,
                    'claim_type' => 'MISSING_ITEM',
                    'quantity_disputed' => 200,
                    'quantity_damaged' => 0,
                    'quantity_missing' => 200,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Verify two separate claims were created, each with 200 quantity
        $claims = Claim::where('distribution_record_id', $distRecord->distribution_record_id)->get();
        $this->assertCount(2, $claims);

        $claimA = $claims->firstWhere('distribution_item_id', $distItemA->distribution_item_id);
        $this->assertNotNull($claimA);
        $this->assertEquals(200.0, (float) $claimA->quantity_disputed);
        $this->assertEquals(200.0, (float) $claimA->quantity_damaged);
        $this->assertSame('DAMAGED_ITEM', $claimA->claim_type);

        $claimB = $claims->firstWhere('distribution_item_id', $distItemB->distribution_item_id);
        $this->assertNotNull($claimB);
        $this->assertEquals(200.0, (float) $claimB->quantity_disputed);
        $this->assertEquals(200.0, (float) $claimB->quantity_missing);
        $this->assertSame('MISSING_ITEM', $claimB->claim_type);
    }

    public function test_follow_up_pr_lineage_and_cumulative_replacement_resolves_claim(): void
    {
        $category = \App\Models\ItemCategory::query()->create(['category_name' => 'Office Supplies 2', 'status' => 'active']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Printer Ink, Black',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        $srOrig = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0001',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office operational supplies',
            'status' => 'RECEIVED',
        ]);

        $distOrig = DistributionRecord::create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $srOrig->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
        ]);

        $distItemOrig = DistributionItem::create([
            'distribution_record_id' => $distOrig->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 50,
            'quantity_released' => 50,
            'quantity_received' => 50,
            'unit' => 'box',
        ]);

        // Create claim for 50 boxes
        $claim = Claim::create([
            'claim_code' => 'CLM-2026-00006',
            'distribution_record_id' => $distOrig->distribution_record_id,
            'distribution_item_id' => $distItemOrig->distribution_item_id,
            'claimed_by' => $this->officeUser->id,
            'office_id' => $this->office->id,
            'claim_type' => 'MISSING_AND_DAMAGED',
            'claim_date' => now()->toDateString(),
            'claim_location' => 'Stock room',
            'quantity_disputed' => 50,
            'quantity_damaged' => 20,
            'quantity_missing' => 30,
            'status' => 'OPEN',
        ]);

        // First replacement delivery & distribution of 30 boxes (SR 0010)
        $srRepl1 = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0010',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'REPLACEMENT',
            'priority_level' => 'NORMAL',
            'purpose' => 'Claim replacement delivery',
            'status' => 'RECEIVED',
        ]);

        $prRepl1 = PurchaseRequest::create([
            'pr_number' => 'PR-2026-09-0008',
            'supply_request_id' => $srRepl1->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'source_claim_id' => $claim->claims_id,
            'source_type' => 'CLAIM_REPLACEMENT_FOLLOW_UP',
            'status' => 'COMPLETED',
        ]);

        $distRepl1 = DistributionRecord::create([
            'distribution_code' => 'DIST-2026-09-0010',
            'supply_request_id' => $srRepl1->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'notes' => 'Automatically released against SR-MPDO-2026-09-0010.',
        ]);

        DistributionItem::create([
            'distribution_record_id' => $distRepl1->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 30,
            'quantity_released' => 30,
            'quantity_received' => 30,
            'unit' => 'box',
        ]);

        // Second replacement delivery & distribution of 20 boxes (SR 0011) linked via sourceSupplyRequest
        $srRepl2 = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0011',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'REPLACEMENT',
            'priority_level' => 'NORMAL',
            'purpose' => 'Claim replacement delivery',
            'status' => 'RECEIVED',
        ]);

        $prRepl2 = PurchaseRequest::create([
            'pr_number' => 'PR-2026-09-0009',
            'supply_request_id' => $srRepl2->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'source_supply_request_id' => $srRepl1->supply_request_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'COMPLETED',
        ]);

        $distRepl2 = DistributionRecord::create([
            'distribution_code' => 'DIST-2026-09-0011',
            'supply_request_id' => $srRepl2->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
        ]);

        DistributionItem::create([
            'distribution_record_id' => $distRepl2->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 20,
            'quantity_released' => 20,
            'quantity_received' => 20,
            'unit' => 'box',
        ]);

        $distService = app(DistributionRecordService::class);

        // Verify SR 0011 resolves to CLM-2026-00006
        $resolvedClaim = $distService->resolveLinkedSourceClaim($distRepl2);
        $this->assertNotNull($resolvedClaim);
        $this->assertEquals($claim->claims_id, $resolvedClaim->claims_id);

        // Verify cumulative replacement quantity is 30 + 20 = 50
        $cumulative = $distService->calculateCumulativeReplacementQuantity($claim);
        $this->assertEquals(50.0, $cumulative);

        // Verify confirming replacement fully resolves the claim
        $claimService = app(\App\Services\Inventory\ClaimService::class);
        $updatedClaim = $claimService->verifyAndResolveClaim($claim, $this->officeUser);

        $this->assertSame('RESOLVED', (string) $updatedClaim->status);
        $this->assertSame('REPLACEMENT_RELEASED', (string) $updatedClaim->resolution_type);
        $this->assertEquals(50.0, (float) $updatedClaim->replacement_quantity);
    }

    public function test_ongoing_claim_on_supply_request_blocks_second_claim_submission_and_dropdown_selection(): void
    {
        $category = \App\Models\ItemCategory::query()->create(['category_name' => 'IT Supplies', 'status' => 'active']);
        $item = Item::create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'ITEM-INK-001',
            'item_name' => 'Black Ink Bottle',
            'default_unit' => 'bottle',
            'status' => 'ACTIVE',
        ]);

        $supplyRequest = SupplyRequest::create([
            'request_code' => 'SR-TEST-CLAIM-BLOCK-001',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Claim Blocker Test',
            'status' => 'SUBMITTED',
        ]);

        $distRecord = DistributionRecord::create([
            'distribution_code' => 'DIS-TEST-CLAIM-BLOCK-001',
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
        ]);

        $distItem = DistributionItem::create([
            'distribution_record_id' => $distRecord->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 10,
            'quantity_released' => 10,
            'quantity_received' => 10,
            'unit' => 'bottle',
        ]);

        $evidenceFile = UploadedFile::fake()->create('defect.jpg', 200, 'image/jpeg');

        // 1. Submit the first claim on this supply request
        $firstClaimResponse = $this->actingAs($this->officeUser)->post(route('office.claims.store'), [
            'distribution_record_id' => $distRecord->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $this->office->id,
            'claim_type' => 'DAMAGED_ITEM',
            'claim_date' => now()->toDateString(),
            'claim_location' => 'Office Storage',
            'quantity_disputed' => 3,
            'quantity_damaged' => 3,
            'quantity_missing' => 0,
            'remarks' => 'First claim for damaged ink bottles.',
            'attachment_file' => $evidenceFile,
        ]);

        $firstClaimResponse->assertSessionHasNoErrors();
        $this->assertCount(1, Claim::where('distribution_record_id', $distRecord->distribution_record_id)->get());
        $claim = Claim::where('distribution_record_id', $distRecord->distribution_record_id)->first();
        $this->assertSame('OPEN', (string) $claim->status);

        // 2. Attempt to submit a second claim while the first claim is still ongoing -> MUST be blocked
        $secondClaimResponse = $this->actingAs($this->officeUser)->post(route('office.claims.store'), [
            'distribution_record_id' => $distRecord->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $this->office->id,
            'claim_type' => 'MISSING_ITEM',
            'claim_date' => now()->toDateString(),
            'claim_location' => 'Office Storage',
            'quantity_disputed' => 2,
            'quantity_damaged' => 0,
            'quantity_missing' => 2,
            'remarks' => 'Attempt to file second claim while first is ongoing.',
            'attachment_file' => $evidenceFile,
        ]);

        $secondClaimResponse->assertSessionHasErrors(['distribution_record_id']);
        $this->assertCount(1, Claim::where('distribution_record_id', $distRecord->distribution_record_id)->get());

        // 3. Verify on the claims create page that this supply request is marked has_ongoing_claim = true
        $createPageResponse = $this->actingAs($this->officeUser)->get(route('office.claims.create', [
            'distribution_record_id' => $distRecord->distribution_record_id,
        ]));

        $createPageResponse->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Claim/Create')
            ->where('preselectedDistributionRecordId', null)
            ->where('distributionRecords', fn ($records) => collect($records)
                ->contains(fn ($r) => (int) $r['distribution_record_id'] === (int) $distRecord->distribution_record_id
                    && $r['has_ongoing_claim'] === true
                    && $r['ongoing_claim_code'] === $claim->claim_code
                )
            )
        );

        // 4. Resolve the claim and verify it unblocks the supply request
        $claim->forceFill(['status' => 'RESOLVED'])->save();

        $unblockedCreatePageResponse = $this->actingAs($this->officeUser)->get(route('office.claims.create'));
        $unblockedCreatePageResponse->assertInertia(fn (Assert $page) => $page
            ->where('distributionRecords', fn ($records) => collect($records)
                ->contains(fn ($r) => (int) $r['distribution_record_id'] === (int) $distRecord->distribution_record_id
                    && $r['has_ongoing_claim'] === false
                )
            )
        );
    }
}

