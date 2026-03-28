<?php

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Office;
use App\Models\PublicDisclosure;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDisclosurePortalTest extends TestCase
{
    use RefreshDatabase;

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

    private function createUser(UserRole $role, Office $office): User
    {
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
        ]);
    }
}
