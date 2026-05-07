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

    public function test_item_category_index_loads_for_gso(): void
    {
        ItemCategory::query()->create([
            'category_name' => 'Index Load Category',
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.item-categories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/ItemCategories/Index')
                ->has('categories.data', 1)
            );
    }

    public function test_item_category_edit_page_loads_for_gso(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Edit Load Category',
            'standard_delivery_days' => 6,
            'description' => 'Editable description',
            'status' => 'active',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->get(route('inventory.item-categories.edit', $category->item_category_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/ItemCategories/Edit')
                ->where('category.item_category_id', $category->item_category_id)
                ->where('category.description', 'Editable description')
            );
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

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('items', [
            'item_id' => $item->item_id,
            'status' => 'inactive',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.item-categories.toggle-status', $category->item_category_id))
            ->assertRedirect();

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.items.toggle-status', $item->item_id))
            ->assertRedirect();

        $this->assertDatabaseHas('items', [
            'item_id' => $item->item_id,
            'status' => 'active',
        ]);
    }

    public function test_deactivating_category_also_deactivates_all_child_items_but_reactivation_does_not_restore_them(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Cascade Status Category',
            'standard_delivery_days' => 4,
            'description' => null,
            'status' => 'active',
        ]);

        $activeItem = Item::query()->create([
            'item_name' => 'Cascade Active Item',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'PCS',
            'description' => null,
            'status' => 'active',
        ]);

        $alreadyInactiveItem = Item::query()->create([
            'item_name' => 'Cascade Inactive Item',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'PCS',
            'description' => null,
            'status' => 'inactive',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.item-categories.toggle-status', $category->item_category_id))
            ->assertRedirect();

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('items', [
            'item_id' => $activeItem->item_id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('items', [
            'item_id' => $alreadyInactiveItem->item_id,
            'status' => 'inactive',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.item-categories.toggle-status', $category->item_category_id))
            ->assertRedirect();

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('items', [
            'item_id' => $activeItem->item_id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('items', [
            'item_id' => $alreadyInactiveItem->item_id,
            'status' => 'inactive',
        ]);
    }

    public function test_item_cannot_be_reactivated_while_parent_category_is_inactive(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Inactive Parent Category',
            'standard_delivery_days' => 2,
            'description' => null,
            'status' => 'inactive',
        ]);

        $item = Item::query()->create([
            'item_name' => 'Blocked Reactivation Item',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'PCS',
            'description' => null,
            'status' => 'inactive',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.items.toggle-status', $item->item_id))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot activate this item because its category is deactivated. Reactivate the category first.');

        $this->assertDatabaseHas('items', [
            'item_id' => $item->item_id,
            'status' => 'inactive',
        ]);
    }

    public function test_gso_can_update_item_category_fields(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Consumables',
            'standard_delivery_days' => 5,
            'description' => 'Original category description',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->gsoOfficer)
            ->put(route('inventory.item-categories.update', $category->item_category_id), [
                'category_name' => 'Office Consumables',
                'standard_delivery_days' => 9,
                'description' => 'Updated category description',
                'status' => 'inactive',
            ]);

        $response->assertRedirect(route('inventory.item-categories.show', $category->item_category_id));
        $response->assertSessionHas('success', 'Item category updated successfully.');

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'category_name' => 'Office Consumables',
            'standard_delivery_days' => 9,
            'description' => 'Updated category description',
            'status' => 'inactive',
        ]);
    }

    public function test_wrong_http_method_for_item_category_update_is_rejected(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Method Guard Category',
            'standard_delivery_days' => 2,
            'description' => null,
            'status' => 'active',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->patch(route('inventory.item-categories.update', $category->item_category_id), [
                'category_name' => 'Patched Category',
                'standard_delivery_days' => 3,
                'description' => 'Should not persist',
                'status' => 'active',
            ])
            ->assertStatus(405);

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'category_name' => 'Method Guard Category',
            'standard_delivery_days' => 2,
        ]);
    }

    public function test_non_gso_roles_cannot_update_item_categories(): void
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Role Guard Category',
            'standard_delivery_days' => 4,
            'description' => 'Original',
            'status' => 'active',
        ]);

        $payload = [
            'category_name' => 'Unauthorized Update',
            'standard_delivery_days' => 8,
            'description' => 'Blocked update',
            'status' => 'inactive',
        ];

        foreach ([$this->adminUser, $this->procurementOfficer, $this->officeUser, $this->executiveUser] as $user) {
            $this->actingAs($user)
                ->put(route('inventory.item-categories.update', $category->item_category_id), $payload)
                ->assertForbidden();
        }

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $category->item_category_id,
            'category_name' => 'Role Guard Category',
            'standard_delivery_days' => 4,
            'description' => 'Original',
            'status' => 'active',
        ]);
    }

    public function test_item_category_update_route_binds_custom_primary_key(): void
    {
        $target = ItemCategory::query()->create([
            'category_name' => 'Binding Target',
            'standard_delivery_days' => 6,
            'description' => 'Target row',
            'status' => 'active',
        ]);

        $other = ItemCategory::query()->create([
            'category_name' => 'Binding Other',
            'standard_delivery_days' => 6,
            'description' => 'Other row',
            'status' => 'active',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->put(route('inventory.item-categories.update', $target->item_category_id), [
                'category_name' => 'Binding Target Updated',
                'standard_delivery_days' => 11,
                'description' => 'Updated via bound key',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('inventory.item-categories.show', $target->item_category_id));

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $target->item_category_id,
            'category_name' => 'Binding Target Updated',
            'standard_delivery_days' => 11,
            'description' => 'Updated via bound key',
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('item_categories', [
            'item_category_id' => $other->item_category_id,
            'category_name' => 'Binding Other',
            'standard_delivery_days' => 6,
            'description' => 'Other row',
            'status' => 'active',
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
