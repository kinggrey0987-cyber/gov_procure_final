<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Office\SupplyRequestService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentNumberingStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected User $officeUser;
    protected User $procurementUser;
    protected User $executiveUser;
    protected Ppmp $ppmp;
    protected Item $item;
    protected PpmpItem $ppmpItem;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Municipal Planning and Development Office',
            'code' => 'MPDO',
            'description' => 'Planning office',
            'is_active' => true,
        ]);

        foreach (UserRole::cases() as $role) {
            Role::query()->firstOrCreate(
                ['code' => $role->value],
                [
                    'name' => str_replace('_', ' ', $role->value),
                    'description' => 'Role for testing',
                    'is_active' => true,
                ]
            );
        }

        $this->officeUser = User::factory()
            ->withRole(UserRole::OFFICE_USER)
            ->forOffice($this->office)
            ->create(['email' => 'mpdo.officer@test.local']);

        $this->procurementUser = User::factory()
            ->withRole(UserRole::PROCUREMENT_OFFICER)
            ->forOffice($this->office)
            ->create(['email' => 'proc.officer@test.local']);

        $this->executiveUser = User::factory()
            ->withRole(UserRole::EXECUTIVE_OFFICER)
            ->forOffice($this->office)
            ->create(['email' => 'mayor@test.local']);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies']);
        $this->item = Item::query()->create([
            'item_name' => 'Bond Paper A4',
            'item_category_id' => $category->item_category_id,
            'unit_cost' => 250.00,
            'default_unit' => 'ream',
            'is_active' => true,
        ]);

        $this->ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'fiscal_year' => now()->year,
            'status' => 'APPROVED',
            'prepared_by' => $this->officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 25000.00,
        ]);

        $this->ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $this->ppmp->ppmp_id,
            'item_id' => $this->item->item_id,
            'item_category_id' => $category->item_category_id,
            'procurement_schedule' => 'Q1',
            'unit_cost' => 250.00,
            'quantity' => 100,
            'estimated_budget' => 25000.00,
        ]);

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'National Store Trading',
            'contact_person' => 'Juan Dela Cruz',
            'is_active' => true,
        ]);
    }

    public function test_sr_po_and_pr_numbering_follow_consistent_prefix_year_month_sequence(): void
    {
        $currentYm = now()->format('Y-m');

        // 1. Supply Request creation generates SR-OFFICE-YYYY-MM-0001
        /** @var SupplyRequestService $srService */
        $srService = app(SupplyRequestService::class);
        $sr1 = $srService->create($this->officeUser, [
            'ppmp_id' => $this->ppmp->ppmp_id,
            'needed_by_date' => now()->addDays(10)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Quarterly planning supply requirements.',
            'priority_level' => 'NORMAL',
            'items' => [
                [
                    'item_id' => $this->item->item_id,
                    'quantity_requested' => 10,
                ],
            ],
        ]);

        $expectedSr1Code = sprintf('SR-MPDO-%s-0001', $currentYm);
        $this->assertSame($expectedSr1Code, $sr1->request_code);

        $sr2 = $srService->create($this->officeUser, [
            'ppmp_id' => $this->ppmp->ppmp_id,
            'needed_by_date' => now()->addDays(10)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Second planning batch.',
            'priority_level' => 'NORMAL',
            'items' => [
                [
                    'item_id' => $this->item->item_id,
                    'quantity_requested' => 5,
                ],
            ],
        ]);

        $expectedSr2Code = sprintf('SR-MPDO-%s-0002', $currentYm);
        $this->assertSame($expectedSr2Code, $sr2->request_code);

        $sr1->update(['status' => 'SUBMITTED']);
        $sr2->update(['status' => 'SUBMITTED']);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $this->ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $this->ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 5,
        ]);

        // 2. Purchase Request creation generates PR-YYYY-MM-0001
        /** @var PurchaseRequestService $prService */
        $prService = app(PurchaseRequestService::class);
        $pr1 = $prService->createFromSupplyRequest($this->procurementUser, [
            'supply_request_id' => $sr1->supply_request_id,
            'remarks' => 'Converted to PR for supplier canvassing.',
        ]);

        $expectedPr1Code = sprintf('PR-%s-0001', $currentYm);
        $this->assertSame($expectedPr1Code, $pr1->pr_number);

        $pr2 = $prService->createFromSupplyRequest($this->procurementUser, [
            'supply_request_id' => $sr2->supply_request_id,
            'remarks' => 'Second PR for procurement.',
        ]);

        $expectedPr2Code = sprintf('PR-%s-0002', $currentYm);
        $this->assertSame($expectedPr2Code, $pr2->pr_number);

        // Move PR1 to processing so it is eligible for PO creation
        $pr1->update(['status' => 'PROCESSING']);
        $pr2->update(['status' => 'PROCESSING']);

        // 3. Purchase Order creation generates PO-YYYY-MM-0001
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        $po1 = $poService->create($this->procurementUser, [
            'purchase_request_id' => $pr1->purchase_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
        ]);

        $expectedPo1Code = sprintf('PO-%s-0001', $currentYm);
        $this->assertSame($expectedPo1Code, $po1->po_number);

        $po2 = $poService->create($this->procurementUser, [
            'purchase_request_id' => $pr2->purchase_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
        ]);

        $expectedPo2Code = sprintf('PO-%s-0002', $currentYm);
        $this->assertSame($expectedPo2Code, $po2->po_number);

        // 4. Verify linked relationship and Inertia API endpoint
        $this->actingAs($this->procurementUser)
            ->get(route('procurement.purchase-orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Index')
                ->has('purchaseOrders.data', 2)
                ->where('purchaseOrders.data.0.po_number', $po2->po_number)
                ->where('purchaseOrders.data.0.purchase_request.pr_number', $pr2->pr_number)
                ->where('purchaseOrders.data.1.po_number', $po1->po_number)
                ->where('purchaseOrders.data.1.purchase_request.pr_number', $pr1->pr_number)
            );
    }

    public function test_procurement_documents_repository_strictly_accepts_finished_purchase_orders(): void
    {
        Storage::fake('public');

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0099',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $this->ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'Document test',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0099',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'PROCESSING',
        ]);

        $draftPo = PurchaseOrder::query()->create([
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->procurementUser->id,
            'po_number' => 'PO-2026-09-0098',
            'procurement_method' => 'SHOPPING',
            'status' => 'DRAFT',
            'total_amount' => 5000.00,
        ]);

        $placedPo = PurchaseOrder::query()->create([
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->procurementUser->id,
            'po_number' => 'PO-2026-09-0099',
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 12500.00,
        ]);

        // 1. Index options only list the finished (PLACED) PO, not the DRAFT PO or PR
        $this->actingAs($this->procurementUser)
            ->get(route('procurement.documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/Documents/Index')
                ->has('relatedRecordOptions', 1)
                ->where('relatedRecordOptions.0.related_reference_id', $placedPo->purchase_order_id)
                ->where('relatedRecordOptions.0.label', 'PO-2026-09-0099 - Municipal Planning and Development Office (PLACED)')
            );

        // 2. Uploading a document directly to a PR under procurement documents is rejected
        $this->actingAs($this->procurementUser)
            ->from(route('procurement.documents.index'))
            ->post(route('documents.store', [
                'related_module' => 'purchase_requests',
                'related_reference_id' => $pr->purchase_request_id,
            ]), [
                'title' => 'Direct PR upload test',
                'document_kind' => 'supplier_quotation',
                'file' => UploadedFile::fake()->create('pr-quotation.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('procurement.documents.index'))
            ->assertSessionHasErrors('related_module');

        // 3. Uploading a document to a DRAFT PO is rejected
        $this->actingAs($this->procurementUser)
            ->from(route('procurement.documents.index'))
            ->post(route('documents.store', [
                'related_module' => 'purchase_orders',
                'related_reference_id' => $draftPo->purchase_order_id,
            ]), [
                'title' => 'Draft PO upload test',
                'document_kind' => 'noa',
                'file' => UploadedFile::fake()->create('draft-noa.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('procurement.documents.index'))
            ->assertSessionHasErrors('related_reference_id');

        // 4. Uploading an official Notice of Award (NOA) to the PLACED PO succeeds
        $this->actingAs($this->procurementUser)
            ->from(route('procurement.documents.index'))
            ->post(route('documents.store', [
                'related_module' => 'purchase_orders',
                'related_reference_id' => $placedPo->purchase_order_id,
            ]), [
                'title' => 'Notice of Award - National Store Trading',
                'document_kind' => 'noa',
                'file' => UploadedFile::fake()->create('noa-national-store.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('procurement.documents.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'related_module' => 'purchase_orders',
            'related_reference_id' => $placedPo->purchase_order_id,
            'title' => 'Notice of Award - National Store Trading',
            'document_kind' => 'noa',
            'file_name' => 'noa-national-store.pdf',
        ]);
    }
}
