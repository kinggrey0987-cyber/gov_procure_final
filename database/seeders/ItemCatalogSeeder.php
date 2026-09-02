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
                'sub_unit' => 'ream',
                'status' => 'active',
            ],
            [
                'item_name' => 'Ballpen',
                'category_name' => 'Office Supplies',
                'description' => 'Black ink ballpoint pen (Box of 50 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 50,
                'sub_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Folder',
                'category_name' => 'Office Supplies',
                'description' => 'Standard filing folder (Pack of 100 pcs).',
                'default_unit' => 'pack',
                'pieces_per_box' => 100,
                'sub_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Logbook',
                'category_name' => 'Office Supplies',
                'description' => 'Bound logbook for attendance and office transactions (Box of 10 books).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'book',
                'status' => 'active',
            ],
            [
                'item_name' => 'Staple Wire',
                'category_name' => 'Office Supplies',
                'description' => 'Heavy-duty staple wire refill (Box of 10,000 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10000,
                'sub_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Heavy Duty Stapler',
                'category_name' => 'Office Supplies',
                'description' => 'Heavy-duty desktop manual stapler (1 pc/box).',
                'default_unit' => 'box',
                'pieces_per_box' => 1,
                'sub_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Flash Drive',
                'category_name' => 'IT Equipment',
                'description' => 'Portable USB storage device, 32GB (Box of 10 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Mouse',
                'category_name' => 'IT Equipment',
                'description' => 'USB optical computer mouse (Box of 10 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Printer Ink',
                'category_name' => 'IT Equipment',
                'description' => 'Compatible printer ink cartridge (Box of 5 cartridges).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'cartridge',
                'status' => 'active',
            ],
            [
                'item_name' => 'Toner Cartridge',
                'category_name' => 'IT Equipment',
                'description' => 'Laser printer toner cartridge (Standard packaging).',
                'default_unit' => 'cartridge',
                'pieces_per_box' => 1,
                'sub_unit' => 'cartridge',
                'status' => 'active',
            ],
            [
                'item_name' => 'HP 680 Tri-Color Ink',
                'category_name' => 'IT Equipment',
                'description' => 'HP 680 original tri-color ink cartridge (Box of 5 cartridges).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'cartridge',
                'status' => 'active',
            ],
            [
                'item_name' => 'Rubbing Alcohol',
                'category_name' => 'Cleaning Supplies',
                'description' => '70% Isopropyl cleaning alcohol (Box of 4 gallons).',
                'default_unit' => 'box',
                'pieces_per_box' => 4,
                'sub_unit' => 'gallon',
                'status' => 'active',
            ],
            [
                'item_name' => 'Detergent Powder',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Laundry and surface cleaning detergent (Box of 24 packs @ 1kg).',
                'default_unit' => 'box',
                'pieces_per_box' => 24,
                'sub_unit' => 'pack',
                'status' => 'active',
            ],
            [
                'item_name' => 'Trash Bag',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Heavy-duty waste disposal bag (Box of 10 rolls @ 50 pcs/roll = 500 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'roll',
                'status' => 'active',
            ],
            [
                'item_name' => 'Safety Gloves',
                'category_name' => 'Maintenance Supplies',
                'description' => 'Heavy-duty industrial safety gloves (Box of 12 pairs).',
                'default_unit' => 'box',
                'pieces_per_box' => 12,
                'sub_unit' => 'pair',
                'status' => 'active',
            ],
            [
                'item_name' => 'Utility Cutter',
                'category_name' => 'Maintenance Supplies',
                'description' => 'Retractable utility cutter (Box of 12 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 12,
                'sub_unit' => 'piece',
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
                    'sub_unit' => $item['sub_unit'] ?? 'piece',
                    'status' => $item['status'],
                ],
            );
        }
    }
}
