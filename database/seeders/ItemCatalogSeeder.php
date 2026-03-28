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
                'description' => 'A4 white copy paper, 80 GSM.',
                'default_unit' => 'ream',
                'status' => 'active',
            ],
            [
                'item_name' => 'Ballpen',
                'category_name' => 'Office Supplies',
                'description' => 'Black ink ballpoint pen.',
                'default_unit' => 'piece',
                'status' => 'active',
            ],
            [
                'item_name' => 'Printer Ink',
                'category_name' => 'IT Equipment',
                'description' => 'Compatible printer ink cartridge.',
                'default_unit' => 'cartridge',
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
                    'status' => $item['status'],
                ],
            );
        }
    }
}
