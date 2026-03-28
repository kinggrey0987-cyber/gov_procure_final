<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected User $gsoOfficer;
    protected User $officeUser;
    protected User $procurementOfficer;
    protected User $adminUser;
    protected User $executiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = $this->createOffice('Main Office', 'MAIN_OFF');
        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
        $this->procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office);
        $this->adminUser = $this->createUser(UserRole::ADMIN, $this->office);
        $this->executiveUser = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office);
    }

    public function test_gso_can_manage_item_categories_and_items(): void
    {
        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.item-categories.create'))
            ->assertOk();

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.item-categories.store'), [
                'category_name' => 'Office Supplies',
                'standard_delivery_days' => 7,
                'description' => 'Frequently requested office consumables',
                'status' => 'active',
            ])
            ->assertRedirect();

        $category = ItemCategory::query()->where('category_name', 'Office Supplies')->firstOrFail();

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.items.store'), [
                'item_name' => 'Bond Paper A4',
                'item_category_id' => $category->item_category_id,
                'default_unit' => 'REAM',
                'description' => '80gsm',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('items', [
            'item_name' => 'Bond Paper A4',
            'item_category_id' => $category->item_category_id,
            'status' => 'active',
        ]);
    }

    public function test_non_gso_roles_cannot_mutate_catalog(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Mutations Guard',
            'standard_delivery_days' => 4,
            'description' => null,
            'status' => 'active',
        ]);

        $payload = [
            'item_name' => 'Unauthorized Item',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'PCS',
            'description' => null,
            'status' => 'active',
        ];

        foreach ([$this->adminUser, $this->procurementOfficer, $this->officeUser, $this->executiveUser] as $user) {
            $this->actingAs($user)
                ->post(route('inventory.items.store'), $payload)
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('items', [
            'item_name' => 'Unauthorized Item',
        ]);
    }

    public function test_item_creation_rejects_unknown_item_category_reference(): void
    {
        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.items.store'), [
                'item_name' => 'Invalid Category Ref Item',
                'item_category_id' => 999999,
                'default_unit' => 'PCS',
                'description' => 'Should fail FK rule',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('item_category_id');
    }

    public function test_catalog_status_toggle_flips_active_and_inactive(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Status Category',
            'standard_delivery_days' => 3,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_name' => 'Status Item',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'PCS',
            'description' => null,
            'status' => 'active',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.item-categories.toggle-status', $category->item_category_id))
            ->assertRedirect();

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.items.toggle-status', $item->item_id))
            ->assertRedirect();

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('items', [
            'item_id' => $item->item_id,
            'status' => 'inactive',
        ]);
    }

    public function test_office_ppmp_create_still_resolves_catalog_items_after_catalog_changes(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'PPMP Compatibility',
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        Item::query()->create([
            'item_name' => 'Compatibility Item',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'PCS',
            'description' => null,
            'status' => 'active',
        ]);

        $this->actingAs($this->officeUser)
            ->get(route('office.ppmp.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Office/Ppmp/Create')
                ->has('itemOptions', 1)
                ->where('itemOptions.0.item_name', 'Compatibility Item')
                ->where('itemOptions.0.item_category_name', 'PPMP Compatibility')
            );
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
                'description' => 'Role for item catalog management tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
