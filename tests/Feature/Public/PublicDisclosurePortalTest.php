<?php

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDisclosurePortalTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    protected Office $office;
    protected User $publicUser;
    protected User $executiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Public Portal Office',
            'code' => 'PUB_PORTAL',
            'description' => 'Public portal office',
            'is_active' => true,
        ]);

        $this->publicUser = $this->createUser(UserRole::PUBLIC, $this->office);
        $this->executiveUser = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office);
    }

    public function test_public_user_sees_only_published_disclosures(): void
    {
        PublicDisclosure::query()->create([
            'title' => 'Published Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Visible publicly',
            'published_by' => $this->executiveUser->id,
            'published_at' => now(),
            'file_path' => '/files/published.pdf',
            'status' => 'PUBLISHED',
        ]);

        $draft = PublicDisclosure::query()->create([
            'title' => 'Draft Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Hidden',
            'published_by' => null,
            'published_at' => null,
            'file_path' => null,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Disclosures/Index')
                ->has('disclosures.data', 1)
            );

        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.show', $draft->disclosure_id))
            ->assertNotFound();
    }

    public function test_public_disclosures_can_be_filtered_by_year_type_and_search(): void
    {
        PublicDisclosure::query()->create([
            'title' => '2026 Office Supplies Procurement Package',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Office supplies package for 2026',
            'published_by' => $this->executiveUser->id,
            'published_at' => '2026-03-15 10:00:00',
            'file_path' => '/files/2026-supplies.pdf',
            'status' => 'PUBLISHED',
        ]);

        PublicDisclosure::query()->create([
            'title' => '2025 IT Equipment Procurement Package',
            'type' => 'PPMP',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'IT equipment package for 2025',
            'published_by' => $this->executiveUser->id,
            'published_at' => '2025-06-20 14:00:00',
            'file_path' => '/files/2025-it.pdf',
            'status' => 'PUBLISHED',
        ]);

        // Filter by Year 2026
        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.index', ['year' => '2026']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Disclosures/Index')
                ->has('disclosures.data', 1)
                ->where('disclosures.data.0.title', '2026 Office Supplies Procurement Package')
                ->where('filters.year', '2026')
            );

        // Filter by Type PPMP
        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.index', ['type' => 'PPMP']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Disclosures/Index')
                ->has('disclosures.data', 1)
                ->where('disclosures.data.0.title', '2025 IT Equipment Procurement Package')
                ->where('filters.type', 'PPMP')
            );

        // Filter by Search Keyword
        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.index', ['search' => 'Supplies']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Disclosures/Index')
                ->has('disclosures.data', 1)
                ->where('disclosures.data.0.title', '2026 Office Supplies Procurement Package')
            );
    }

    public function test_welcome_page_uses_published_public_data_only(): void
    {
        $published = PublicDisclosure::query()->create([
            'title' => 'Published Landing Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Visible on public landing page',
            'published_by' => $this->executiveUser->id,
            'published_at' => now(),
            'file_path' => '/files/published-landing.pdf',
            'status' => 'PUBLISHED',
        ]);

        $draft = PublicDisclosure::query()->create([
            'title' => 'Draft Landing Disclosure',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Should not be visible',
            'published_by' => null,
            'published_at' => null,
            'file_path' => null,
            'status' => 'DRAFT',
        ]);

        Document::query()->create([
            'uploaded_by' => $this->executiveUser->id,
            'title' => 'Published Attachment',
            'file_name' => 'published-attachment.pdf',
            'file_path' => 'public_disclosures/published-attachment.pdf',
            'related_module' => 'public_disclosures',
            'related_reference_id' => $published->disclosure_id,
            'uploaded_at' => now(),
        ]);

        Document::query()->create([
            'uploaded_by' => $this->executiveUser->id,
            'title' => 'Draft Attachment',
            'file_name' => 'draft-attachment.pdf',
            'file_path' => 'public_disclosures/draft-attachment.pdf',
            'related_module' => 'public_disclosures',
            'related_reference_id' => $draft->disclosure_id,
            'uploaded_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('summaryStats.0.value', '1')
                ->has('latestDisclosures', 1)
                ->where('latestDisclosures.0.title', 'Published Landing Disclosure')
                ->has('publicReports', 1)
                ->where('publicReports.0.title', 'Published Attachment')
            );
    }

    public function test_public_document_download_requires_published_disclosure(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('public_disclosures/published.pdf', 'published');
        Storage::disk('public')->put('public_disclosures/draft.pdf', 'draft');

        $published = PublicDisclosure::query()->create([
            'title' => 'Published Disclosure Download',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Published document should be downloadable',
            'published_by' => $this->executiveUser->id,
            'published_at' => now(),
            'file_path' => 'public_disclosures/published.pdf',
            'status' => 'PUBLISHED',
        ]);

        $draft = PublicDisclosure::query()->create([
            'title' => 'Draft Disclosure Download',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Draft document should not be downloadable',
            'published_by' => null,
            'published_at' => null,
            'file_path' => 'public_disclosures/draft.pdf',
            'status' => 'DRAFT',
        ]);

        $publishedDocument = Document::query()->create([
            'uploaded_by' => $this->executiveUser->id,
            'title' => 'Published Doc Attachment',
            'file_name' => 'published.pdf',
            'file_path' => 'public_disclosures/published.pdf',
            'related_module' => 'public_disclosures',
            'related_reference_id' => $published->disclosure_id,
            'uploaded_at' => now(),
        ]);

        $draftDocument = Document::query()->create([
            'uploaded_by' => $this->executiveUser->id,
            'title' => 'Draft Doc Attachment',
            'file_name' => 'draft.pdf',
            'file_path' => 'public_disclosures/draft.pdf',
            'related_module' => 'public_disclosures',
            'related_reference_id' => $draft->disclosure_id,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.download', $published->disclosure_id))
            ->assertOk();

        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.download', $draft->disclosure_id))
            ->assertNotFound();

        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.documents.download', [
                'public_disclosure' => $published->disclosure_id,
                'document' => $publishedDocument->document_id,
            ]))
            ->assertOk();

        $this->actingAs($this->publicUser)
            ->get(route('public.disclosures.documents.download', [
                'public_disclosure' => $draft->disclosure_id,
                'document' => $draftDocument->document_id,
            ]))
            ->assertNotFound();
    }

    public function test_welcome_metrics_ignore_internal_records_without_published_disclosures(): void
    {
        Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->executiveUser->id,
            'approved_by' => $this->executiveUser->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'total_estimated_budget' => 1500000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => 'Internal approved record only',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('summaryStats.0.value', '0')
                ->has('latestDisclosures', 0)
                ->has('publicReports', 0)
            );
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $this->userSequence++;

        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for public disclosure tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'emp_id' => sprintf('EMP-PUB-%05d', $this->userSequence),
        ]);
    }
}
