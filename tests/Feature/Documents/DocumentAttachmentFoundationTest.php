<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Document;
use App\Models\Office;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAttachmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $officeA;
    protected Office $officeB;
    protected User $officeUserA;
    protected User $officeUserB;
    protected User $procurementUser;
    protected User $gsoUser;
    protected User $executiveUser;
    protected User $publicUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->officeA = $this->createOffice('Office A', 'DOC_OA');
        $this->officeB = $this->createOffice('Office B', 'DOC_OB');

        $this->officeUserA = $this->createUser(UserRole::OFFICE_USER, $this->officeA);
        $this->officeUserB = $this->createUser(UserRole::OFFICE_USER, $this->officeB);
        $this->procurementUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->officeA);
        $this->gsoUser = $this->createUser(UserRole::GSO_OFFICER, $this->officeA);
        $this->executiveUser = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->officeA);
        $this->publicUser = $this->createUser(UserRole::PUBLIC, $this->officeA);
        $this->adminUser = $this->createUser(UserRole::ADMIN, $this->officeA);
    }

    public function test_authorized_upload_to_purchase_order_persists_exact_document_metadata_and_linkage(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurementUser, $this->officeUserA);

        $this->actingAs($this->procurementUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'PO BAC Resolution',
                'file' => UploadedFile::fake()->create('po-resolution.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::query()->firstOrFail();

        $this->assertDatabaseHas('documents', [
            'document_id' => $document->document_id,
            'uploaded_by' => $this->procurementUser->id,
            'title' => 'PO BAC Resolution',
            'file_name' => 'po-resolution.pdf',
            'related_module' => 'purchase_orders',
            'related_reference_id' => $purchaseOrder->purchase_order_id,
        ]);
        $this->assertNotNull($document->uploaded_at);
        $this->assertNotEmpty($document->file_path);
        $this->assertStringStartsWith('documents/purchase_orders/'.$purchaseOrder->purchase_order_id.'/', (string) $document->file_path);
    }

    public function test_unauthorized_upload_to_purchase_order_is_rejected_for_non_procurement_roles(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurementUser, $this->officeUserA);

        $this->actingAs($this->officeUserA)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'Unauthorized Upload',
                'file' => UploadedFile::fake()->create('unauthorized.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'Admin Upload',
                'file' => UploadedFile::fake()->create('admin.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_own_office_access_enforcement_for_office_user_supply_request_attachments(): void
    {
        $ownSupplyRequest = $this->createSupplyRequest($this->officeA, $this->officeUserA);
        $otherSupplyRequest = $this->createSupplyRequest($this->officeB, $this->officeUserB);

        $this->actingAs($this->officeUserA)
            ->post(route('documents.store', ['related_module' => 'supply_requests', 'related_reference_id' => $ownSupplyRequest->supply_request_id]), [
                'title' => 'Own Office Supporting File',
                'file' => UploadedFile::fake()->create('own-office.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->officeUserA)
            ->post(route('documents.store', ['related_module' => 'supply_requests', 'related_reference_id' => $otherSupplyRequest->supply_request_id]), [
                'title' => 'Cross Office Upload',
                'file' => UploadedFile::fake()->create('cross-office.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_procurement_and_logistics_role_boundaries_are_enforced(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurementUser, $this->officeUserA);
        $delivery = $this->createDelivery($purchaseOrder, $this->procurementUser);

        $this->actingAs($this->procurementUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'Procurement PO Attachment',
                'file' => UploadedFile::fake()->create('proc-po.pdf', 15, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->gsoUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'GSO PO Attachment',
                'file' => UploadedFile::fake()->create('gso-po.pdf', 15, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($this->gsoUser)
            ->post(route('documents.store', ['related_module' => 'deliveries', 'related_reference_id' => $delivery->delivery_id]), [
                'title' => 'Delivery Inspection Report',
                'file' => UploadedFile::fake()->create('inspection.pdf', 15, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->procurementUser)
            ->post(route('documents.store', ['related_module' => 'deliveries', 'related_reference_id' => $delivery->delivery_id]), [
                'title' => 'Proc Delivery Upload',
                'file' => UploadedFile::fake()->create('proc-delivery.pdf', 15, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_public_cannot_access_non_public_disclosure_files_and_can_access_published_only(): void
    {
        $draftDisclosure = PublicDisclosure::query()->create([
            'title' => 'Draft Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Not public yet',
            'published_by' => null,
            'published_at' => null,
            'file_path' => null,
            'status' => 'DRAFT',
        ]);

        $publishedDisclosure = PublicDisclosure::query()->create([
            'title' => 'Published Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Visible publicly',
            'published_by' => $this->executiveUser->id,
            'published_at' => now(),
            'file_path' => null,
            'status' => 'PUBLISHED',
        ]);

        $draftPath = 'documents/public_disclosures/'.$draftDisclosure->disclosure_id.'/draft-file.pdf';
        $publishedPath = 'documents/public_disclosures/'.$publishedDisclosure->disclosure_id.'/published-file.pdf';
        Storage::disk('public')->put($draftPath, 'draft');
        Storage::disk('public')->put($publishedPath, 'published');

        $draftDocument = Document::query()->create([
            'uploaded_by' => $this->executiveUser->id,
            'title' => 'Draft Attachment',
            'file_name' => 'draft-file.pdf',
            'file_path' => $draftPath,
            'related_module' => 'public_disclosures',
            'related_reference_id' => $draftDisclosure->disclosure_id,
            'uploaded_at' => now(),
        ]);

        $publishedDocument = Document::query()->create([
            'uploaded_by' => $this->executiveUser->id,
            'title' => 'Published Attachment',
            'file_name' => 'published-file.pdf',
            'file_path' => $publishedPath,
            'related_module' => 'public_disclosures',
            'related_reference_id' => $publishedDisclosure->disclosure_id,
            'uploaded_at' => now(),
        ]);

        $this->get(route('public.disclosures.documents.download', [
            'public_disclosure' => $draftDisclosure->disclosure_id,
            'document' => $draftDocument->document_id,
        ]))->assertNotFound();

        $this->get(route('public.disclosures.documents.download', [
            'public_disclosure' => $publishedDisclosure->disclosure_id,
            'document' => $publishedDocument->document_id,
        ]))->assertOk();
    }

    public function test_delete_action_requires_authorization(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurementUser, $this->officeUserA);

        $this->actingAs($this->procurementUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'Delete Me',
                'file' => UploadedFile::fake()->create('delete-me.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::query()->firstOrFail();

        $this->actingAs($this->officeUserA)
            ->delete(route('documents.destroy', $document->document_id))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['document_id' => $document->document_id]);

        $this->actingAs($this->procurementUser)
            ->delete(route('documents.destroy', $document->document_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('documents', ['document_id' => $document->document_id]);
    }

    public function test_related_module_and_related_reference_listing_is_scoped_correctly(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurementUser, $this->officeUserA);
        $otherPurchaseOrder = $this->createPurchaseOrder($this->officeA, $this->procurementUser, $this->officeUserA);

        $this->actingAs($this->procurementUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $purchaseOrder->purchase_order_id]), [
                'title' => 'PO A Attachment',
                'file' => UploadedFile::fake()->create('po-a.pdf', 15, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->procurementUser)
            ->post(route('documents.store', ['related_module' => 'purchase_orders', 'related_reference_id' => $otherPurchaseOrder->purchase_order_id]), [
                'title' => 'PO B Attachment',
                'file' => UploadedFile::fake()->create('po-b.pdf', 15, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->procurementUser)
            ->get(route('documents.index', [
                'related_module' => 'purchase_orders',
                'related_reference_id' => $purchaseOrder->purchase_order_id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'documents');
    }

    private function createOffice(string $name, string $code): Office
    {
        return Office::query()->create([
            'name' => $name,
            'code' => $code,
            'description' => $name.' description',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for document tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }

    private function createSupplyRequest(Office $office, User $officeUser): SupplyRequest
    {
        return SupplyRequest::query()->create([
            'request_code' => 'SR-DOC-'.uniqid(),
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Document attachment test',
            'priority_level' => 'MEDIUM',
            'status' => 'DRAFT',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    private function createPurchaseOrder(Office $office, User $procurementUser, User $officeUser): PurchaseOrder
    {
        $supplyRequest = $this->createSupplyRequest($office, $officeUser);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementUser->id,
            'status' => 'PROCESSING',
            'remarks' => 'Document test PR',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Doc Supplier '.uniqid(),
            'contact_person' => 'Contact',
            'email' => uniqid('doc-supplier', true).'@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        return PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementUser->id,
            'po_number' => 'PO-DOC-'.uniqid(),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'DRAFT',
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 1000,
            'terms_notes' => null,
            'remarks' => null,
        ]);
    }

    private function createDelivery(PurchaseOrder $purchaseOrder, User $receivedBy): Delivery
    {
        return Delivery::query()->create([
            'delivery_code' => 'DEL-DOC-'.uniqid(),
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->toDateString(),
            'received_by' => $receivedBy->id,
            'receipt_status' => 'PENDING',
            'remarks' => null,
        ]);
    }
}
