<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureMustChangePassword;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Models\Document;
use App\Models\Office;
use App\Models\PublicDisclosure;
use App\Models\PublicDisclosureSource;
use App\Models\Role;
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
}
