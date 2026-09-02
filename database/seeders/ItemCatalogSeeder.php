<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCatalogSeeder extends Seeder
{
    /**
     * Seed minimal item categories and items for PPMP testing.
     */
    public function run(): void
    {
        $categories = collect([
            [
                'category_name' => 'Office Supplies',
                'standard_delivery_days' => 7,
                'description' => 'Common office consumables and stationery.',
                'status' => 'active',
            ],
            [
                'category_name' => 'IT Equipment',
                'standard_delivery_days' => 14,
                'description' => 'Computing devices and technology peripherals.',
                'status' => 'active',
            ],
            [
                'category_name' => 'Cleaning Supplies',
                'standard_delivery_days' => 5,
                'description' => 'Janitorial and sanitation-related items.',
                'status' => 'active',
            ],
            [
                'category_name' => 'Maintenance Supplies',
                'standard_delivery_days' => 10,
                'description' => 'Basic maintenance, utility, and replenishment stock.',
                'status' => 'active',
            ],
        ])->mapWithKeys(function (array $category): array {
            $record = ItemCategory::query()->updateOrCreate(
                ['category_name' => $category['category_name']],
                [
                    'standard_delivery_days' => $category['standard_delivery_days'],
                    'description' => $category['description'],
                    'status' => $category['status'],
                ],
            );

            return [$record->category_name => (int) $record->item_category_id];
        });

        $items = [
            [
                'item_name' => 'Bond Paper',
                'category_name' => 'Office Supplies',
                'description' => 'A4 white copy paper, 80 GSM (Box of 5 reams).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'status' => 'active',
            ],
            [
                'item_name' => 'Ballpen',
                'category_name' => 'Office Supplies',
                'description' => 'Black ink ballpoint pen (Box of 50 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 50,
                'status' => 'active',
            ],
            [
                'item_name' => 'Folder',
                'category_name' => 'Office Supplies',
                'description' => 'Standard filing folder (Pack of 100 pcs).',
                'default_unit' => 'pack',
                'pieces_per_box' => 100,
                'status' => 'active',
            ],
            [
                'item_name' => 'Logbook',
                'category_name' => 'Office Supplies',
                'description' => 'Bound logbook for attendance and office transactions.',
                'default_unit' => 'book',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Staple Wire',
                'category_name' => 'Office Supplies',
                'description' => 'Heavy-duty staple wire refill (Box of 10,000 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10000,
                'status' => 'active',
            ],
            [
                'item_name' => 'Flash Drive',
                'category_name' => 'IT Equipment',
                'description' => 'Portable USB storage device (32GB).',
                'default_unit' => 'piece',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Mouse',
                'category_name' => 'IT Equipment',
                'description' => 'USB computer mouse.',
                'default_unit' => 'piece',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Printer Ink',
                'category_name' => 'IT Equipment',
                'description' => 'Compatible printer ink cartridge.',
                'default_unit' => 'cartridge',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Toner Cartridge',
                'category_name' => 'IT Equipment',
                'description' => 'Laser printer toner cartridge.',
                'default_unit' => 'cartridge',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Rubbing Alcohol',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Isopropyl cleaning solution (Gallon).',
                'default_unit' => 'gallon',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Detergent Powder',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Laundry and surface detergent (Pack of 1kg).',
                'default_unit' => 'pack',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Trash Bag',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Heavy-duty waste disposal bag (Roll of 50 pcs).',
                'default_unit' => 'roll',
                'pieces_per_box' => 50,
                'status' => 'active',
            ],
            [
                'item_name' => 'Safety Gloves',
                'category_name' => 'Maintenance Supplies',
                'description' => 'Utility safety gloves for handling materials.',
                'default_unit' => 'pair',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
            [
                'item_name' => 'Utility Cutter',
                'category_name' => 'Maintenance Supplies',
                'description' => 'Retractable utility cutter for packaging and office use.',
                'default_unit' => 'piece',
                'pieces_per_box' => 1,
                'status' => 'active',
            ],
        ];

        foreach ($items as $item) {
            $categoryId = $categories->get($item['category_name']);
            if (! $categoryId) {
                continue;
            }

            Item::query()->updateOrCreate(
                ['item_name' => $item['item_name']],
                [
                    'item_category_id' => $categoryId,
                    'description' => $item['description'],
                    'default_unit' => $item['default_unit'],
                    'pieces_per_box' => $item['pieces_per_box'] ?? 1,
                    'status' => $item['status'],
                ],
            );
        }
    }
}
