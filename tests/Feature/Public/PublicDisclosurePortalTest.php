<?php

namespace Tests\Feature\Public;

use App\Enums\UserRole;
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
