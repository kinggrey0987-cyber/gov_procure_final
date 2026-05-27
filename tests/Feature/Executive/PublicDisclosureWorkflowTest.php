<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureMustChangePassword;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Models\Document;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PublicDisclosure;
use App\Models\PublicDisclosureSource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDisclosureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    private Office $office;
    private User $executiveUser;
    private User $officeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware([
            EnsureTwoFactorVerified::class,
            EnsureMustChangePassword::class,
        ]);

        Storage::fake('public');

        $this->office = Office::query()->create([
            'name' => 'Executive Office',
            'code' => 'EXEC_OFC',
            'description' => 'Executive office for disclosure workflow tests',
            'is_active' => true,
        ]);

        $this->executiveUser = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
    }

    public function test_exec_can_create_draft_disclosure(): void
    {
        $response = $this->actingAs($this->executiveUser)->post(route('executive.disclosures.store'), [
            'title' => 'Public PPMP Summary',
            'type' => 'PPMP_SUMMARY',
            'reference_type' => 'App\\Models\\Ppmp',
            'reference_id' => 1001,
            'description' => 'Approved annual PPMP summary for public publication.',
            'status' => 'DRAFT',
        ]);

        $response->assertRedirect();

        $disclosure = PublicDisclosure::query()->firstOrFail();
        $this->assertSame('DRAFT', $disclosure->status);
        $this->assertNull($disclosure->published_by);
        $this->assertNull($disclosure->published_at);
    }

    public function test_exec_can_publish_disclosure_and_metadata_is_set(): void
    {
        $disclosure = PublicDisclosure::query()->create([
            'title' => 'Draft Public Report',
            'type' => 'PROCUREMENT_SUMMARY',
            'reference_type' => 'App\\Models\\PurchaseOrder',
            'reference_id' => 4001,
            'description' => 'Draft for publication',
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->executiveUser)->put(route('executive.disclosures.publish', $disclosure->disclosure_id), [
            'remarks' => 'Executive reviewed and approved for publication.',
        ]);

        $response->assertRedirect();

        $disclosure->refresh();
        $this->assertSame('PUBLISHED', $disclosure->status);
        $this->assertSame($this->executiveUser->id, $disclosure->published_by);
        $this->assertNotNull($disclosure->published_at);
    }

    public function test_non_exec_cannot_create_disclosure(): void
    {
        $response = $this->actingAs($this->officeUser)->post(route('executive.disclosures.store'), [
            'title' => 'Unauthorized Draft',
            'type' => 'PPMP_SUMMARY',
            'status' => 'DRAFT',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('public_disclosures', 0);
    }

    public function test_exec_create_disclosure_without_uploaded_files_generates_source_attachment(): void
    {
        $response = $this->actingAs($this->executiveUser)->post(route('executive.disclosures.store'), [
            'title' => 'PR Week 4 April',
            'type' => 'PROCUREMENT_SUMMARY',
            'reference_type' => 'App\\Models\\PurchaseRequest',
            'reference_id' => 404,
            'description' => 'Disclosure for procurement records in the fourth week of April.',
            'status' => 'DRAFT',
        ]);

        $response->assertRedirect();

        $disclosure = PublicDisclosure::query()->firstOrFail();

        $attachment = Document::query()
            ->where('related_module', 'public_disclosures')
            ->where('related_reference_id', (int) $disclosure->disclosure_id)
            ->where('document_kind', 'OFFICIAL_DISCLOSURE_PACKAGE')
            ->first();

        $this->assertNotNull($attachment);
        $this->assertStringContainsString('disclosure-'.$disclosure->disclosure_id.'-official-package', (string) $attachment->file_name);
        $this->assertStringContainsString('documents/public_disclosures/'.$disclosure->disclosure_id.'/', (string) $attachment->file_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $attachment->file_path));
        $this->assertDatabaseHas('public_disclosure_sources', [
            'disclosure_id' => (int) $disclosure->disclosure_id,
            'source_role' => 'PRIMARY',
        ]);
    }

    public function test_official_disclosure_package_includes_public_safe_ppmp_item_details(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'standard_delivery_days' => 7,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => (int) $category->item_category_id,
            'item_name' => 'Bond Paper A4',
            'description' => 'Public-safe item description.',
            'default_unit' => 'ream',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => (int) $this->office->id,
            'prepared_by' => (int) $this->officeUser->id,
            'approved_by' => (int) $this->executiveUser->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'total_estimated_budget' => 7500,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
        ]);

        PpmpItem::query()->create([
            'ppmp_id' => (int) $ppmp->ppmp_id,
            'item_id' => (int) $item->item_id,
            'item_category_id' => (int) $category->item_category_id,
            'quantity' => 50,
            'unit_cost' => 150,
            'estimated_budget' => 7500,
            'procurement_schedule' => 'Q1',
            'remarks' => 'Internal remarks should not be rendered in public item rows.',
        ]);

        $response = $this->actingAs($this->executiveUser)->post(route('executive.disclosures.store'), [
            'title' => 'Approved PPMP Summary',
            'type' => 'PPMP_SUMMARY',
            'reference_type' => Ppmp::class,
            'reference_id' => (int) $ppmp->ppmp_id,
            'description' => 'Approved annual PPMP summary for public publication.',
            'status' => 'PUBLISHED',
        ]);

        $response->assertRedirect();

        $disclosure = PublicDisclosure::query()->firstOrFail();
        $this->assertSame('PUBLISHED', $disclosure->status);

        $attachment = Document::query()
            ->where('related_module', 'public_disclosures')
            ->where('related_reference_id', (int) $disclosure->disclosure_id)
            ->where('document_kind', 'OFFICIAL_DISCLOSURE_PACKAGE')
            ->firstOrFail();

        $content = Storage::disk('public')->get((string) $attachment->file_path);

        $this->assertSame('Approved PPMP Summary', $disclosure->title);
        $this->assertSame('pdf', pathinfo((string) $attachment->file_name, PATHINFO_EXTENSION));
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(1000, strlen($content));
        $this->assertStringNotContainsString('Internal remarks should not be rendered', $content);
    }

    public function test_exec_can_attach_procurement_upload_to_draft_public_disclosure(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->office, $this->executiveUser, $this->officeUser);
        $sourcePath = 'documents/purchase_orders/'.$purchaseOrder->purchase_order_id.'/signed-po.pdf';
        Storage::disk('public')->put($sourcePath, 'signed purchase order copy');

        $sourceDocument = Document::query()->create([
            'uploaded_by' => (int) $this->executiveUser->id,
            'title' => 'Signed PO Copy',
            'file_name' => 'signed-po.pdf',
            'document_kind' => 'signed_po',
            'file_path' => $sourcePath,
            'related_module' => 'purchase_orders',
            'related_reference_id' => (int) $purchaseOrder->purchase_order_id,
            'uploaded_at' => now(),
        ]);

        $disclosure = PublicDisclosure::query()->create([
            'title' => 'PO Disclosure Package',
            'type' => 'PROCUREMENT_SUMMARY',
            'reference_type' => PurchaseOrder::class,
            'reference_id' => (int) $purchaseOrder->purchase_order_id,
            'description' => 'Draft public release for PO documents.',
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->executiveUser)->post(
            route('executive.disclosures.attach-procurement-documents', $disclosure->disclosure_id),
            ['document_ids' => [(int) $sourceDocument->document_id]],
        );

        $response->assertRedirect(route('executive.disclosures.show', $disclosure->disclosure_id));

        $attachment = Document::query()
            ->where('related_module', 'public_disclosures')
            ->where('related_reference_id', (int) $disclosure->disclosure_id)
            ->where('document_kind', 'SOURCE_PROCUREMENT_DOCUMENT')
            ->firstOrFail();

        $this->assertSame('Public Release Copy - Signed PO Copy', (string) $attachment->title);
        $this->assertSame('procurement-'.$sourceDocument->document_id.'-signed-po.pdf', (string) $attachment->file_name);
        $this->assertStringStartsWith('documents/public_disclosures/'.$disclosure->disclosure_id.'/', (string) $attachment->file_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $attachment->file_path));
        $this->assertSame('signed purchase order copy', Storage::disk('public')->get((string) $attachment->file_path));
        $this->assertDatabaseHas('documents', [
            'document_id' => (int) $sourceDocument->document_id,
            'related_module' => 'purchase_orders',
        ]);
    }

    public function test_procurement_document_attachment_requires_matching_disclosure_source(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->office, $this->executiveUser, $this->officeUser);
        $otherPurchaseOrder = $this->createPurchaseOrder($this->office, $this->executiveUser, $this->officeUser);
        $sourcePath = 'documents/purchase_orders/'.$otherPurchaseOrder->purchase_order_id.'/other-po.pdf';
        Storage::disk('public')->put($sourcePath, 'other po copy');

        $otherDocument = Document::query()->create([
            'uploaded_by' => (int) $this->executiveUser->id,
            'title' => 'Other PO Copy',
            'file_name' => 'other-po.pdf',
            'document_kind' => 'signed_po',
            'file_path' => $sourcePath,
            'related_module' => 'purchase_orders',
            'related_reference_id' => (int) $otherPurchaseOrder->purchase_order_id,
            'uploaded_at' => now(),
        ]);

        $disclosure = PublicDisclosure::query()->create([
            'title' => 'PO Disclosure Package',
            'type' => 'PROCUREMENT_SUMMARY',
            'reference_type' => PurchaseOrder::class,
            'reference_id' => (int) $purchaseOrder->purchase_order_id,
            'description' => 'Draft public release for PO documents.',
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->executiveUser)->post(
            route('executive.disclosures.attach-procurement-documents', $disclosure->disclosure_id),
            ['document_ids' => [(int) $otherDocument->document_id]],
        );

        $response->assertSessionHasErrors('document_ids');
        $this->assertDatabaseMissing('documents', [
            'related_module' => 'public_disclosures',
            'related_reference_id' => (int) $disclosure->disclosure_id,
            'document_kind' => 'SOURCE_PROCUREMENT_DOCUMENT',
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $this->userSequence++;

        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for executive disclosure workflow tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'emp_id' => sprintf('EMP-EXEC-%05d', $this->userSequence),
        ]);
    }

    private function createPurchaseOrder(Office $office, User $createdBy, User $officeUser): PurchaseOrder
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-EXEC-DISC-'.uniqid(),
            'office_id' => (int) $office->id,
            'requested_by' => (int) $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Executive disclosure procurement document test',
            'priority_level' => 'MEDIUM',
            'status' => 'APPROVED',
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'office_id' => (int) $office->id,
            'requested_by' => (int) $createdBy->id,
            'status' => 'PROCESSING',
            'remarks' => 'Disclosure document source PR',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Executive Disclosure Supplier '.uniqid(),
            'contact_person' => 'Disclosure Contact',
            'email' => uniqid('exec-disclosure-supplier', true).'@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        return PurchaseOrder::query()->create([
            'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'supplier_id' => (int) $supplier->supplier_id,
            'created_by' => (int) $createdBy->id,
            'po_number' => 'PO-EXEC-DISC-'.uniqid(),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'DRAFT',
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 1000,
        ]);
    }
}
