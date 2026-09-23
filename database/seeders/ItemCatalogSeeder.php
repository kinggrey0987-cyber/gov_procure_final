<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemSpecification;
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
                'item_name' => 'Bond Paper, A4 (80 GSM)',
                'category_name' => 'Office Supplies',
                'description' => 'A4 white copy paper, 80 GSM, 500 sheets/ream (Box of 5 reams).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'ream',
                'status' => 'active',
                'specifications' => [
                    'A4 (210mm x 297mm), 80 GSM, 500 sheets/ream (Box of 5 reams)',
                ],
            ],
            [
                'item_name' => 'Bond Paper, Legal (80 GSM)',
                'category_name' => 'Office Supplies',
                'description' => 'Legal white copy paper, 80 GSM, 500 sheets/ream (Box of 5 reams).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'ream',
                'status' => 'active',
                'specifications' => [
                    'Legal (216mm x 330mm), 80 GSM, 500 sheets/ream (Box of 5 reams)',
                ],
            ],
            [
                'item_name' => 'Folder, Tagboard (Legal size)',
                'category_name' => 'Office Supplies',
                'description' => 'Standard legal filing folder, 14pt tagboard (Pack of 100 pcs).',
                'default_unit' => 'pack',
                'pieces_per_box' => 100,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Tagboard, Legal size (Pack of 100 pcs)',
                ],
            ],
            [
                'item_name' => 'Folder, Tagboard (A4 size)',
                'category_name' => 'Office Supplies',
                'description' => 'Standard A4 filing folder, 14pt tagboard (Pack of 100 pcs).',
                'default_unit' => 'pack',
                'pieces_per_box' => 100,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Tagboard, A4 size (Pack of 100 pcs)',
                ],
            ],
            [
                'item_name' => 'Ballpen, Black',
                'category_name' => 'Office Supplies',
                'description' => 'Black 0.5mm ballpoint pen (Box of 50 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 50,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Black, 0.5mm ballpoint (Box of 50 pcs)',
                ],
            ],
            [
                'item_name' => 'Ballpen, Blue',
                'category_name' => 'Office Supplies',
                'description' => 'Blue 0.5mm ballpoint pen (Box of 50 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 50,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Blue, 0.5mm ballpoint (Box of 50 pcs)',
                ],
            ],
            [
                'item_name' => 'Ballpen, Red',
                'category_name' => 'Office Supplies',
                'description' => 'Red 0.5mm ballpoint pen (Box of 50 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 50,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Red, 0.5mm ballpoint (Box of 50 pcs)',
                ],
            ],
            [
                'item_name' => 'Logbook',
                'category_name' => 'Office Supplies',
                'description' => 'Bound logbook for attendance and office transactions (Box of 10 books).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'book',
                'status' => 'active',
                'specifications' => [
                    '300 pages, 210mm x 297mm, hardbound (Box of 10 books)',
                ],
            ],
            [
                'item_name' => 'Staple Wire',
                'category_name' => 'Office Supplies',
                'description' => 'Heavy-duty staple wire refill (Box of 10,000 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10000,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Standard #35 (26/6), heavy-duty (Box of 10,000 pcs)',
                ],
            ],
            [
                'item_name' => 'Heavy Duty Stapler',
                'category_name' => 'Office Supplies',
                'description' => 'Heavy-duty desktop manual stapler (1 pc/box).',
                'default_unit' => 'box',
                'pieces_per_box' => 1,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Manual desktop stapler, capacity 100 sheets (1 pc/box)',
                ],
            ],
            [
                'item_name' => 'Flash Drive, 32GB',
                'category_name' => 'IT Equipment',
                'description' => 'Portable USB 3.0 storage device 32GB (Box of 10 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'USB 3.0, 32GB capacity (Box of 10 pcs)',
                ],
            ],
            [
                'item_name' => 'Flash Drive, 64GB',
                'category_name' => 'IT Equipment',
                'description' => 'Portable USB 3.0 storage device 64GB (Box of 10 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'USB 3.0, 64GB capacity (Box of 10 pcs)',
                ],
            ],
            [
                'item_name' => 'Mouse, USB Optical',
                'category_name' => 'IT Equipment',
                'description' => 'USB optical computer mouse (Box of 10 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'USB optical wired mouse, 1000 DPI (Box of 10 pcs)',
                ],
            ],
            [
                'item_name' => 'Printer Ink, Black',
                'category_name' => 'IT Equipment',
                'description' => 'Compatible black printer ink bottle (Box of 5 cartridges).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'cartridge',
                'status' => 'active',
                'specifications' => [
                    'Black compatible ink bottle (Box of 5 cartridges)',
                ],
            ],
            [
                'item_name' => 'Printer Ink, Color Set',
                'category_name' => 'IT Equipment',
                'description' => 'Compatible color printer ink bottle set (Box of 5 cartridges).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'cartridge',
                'status' => 'active',
                'specifications' => [
                    'Color compatible ink bottle set (Box of 5 cartridges)',
                ],
            ],
            [
                'item_name' => 'Toner Cartridge',
                'category_name' => 'IT Equipment',
                'description' => 'Laser printer toner cartridge (Standard packaging).',
                'default_unit' => 'cartridge',
                'pieces_per_box' => 1,
                'sub_unit' => 'cartridge',
                'status' => 'active',
                'specifications' => [
                    'High-yield monochrome laser toner cartridge (Standard packaging)',
                ],
            ],
            [
                'item_name' => 'HP 680 Tri-Color Ink',
                'category_name' => 'IT Equipment',
                'description' => 'HP 680 original tri-color ink cartridge (Box of 5 cartridges).',
                'default_unit' => 'box',
                'pieces_per_box' => 5,
                'sub_unit' => 'cartridge',
                'status' => 'active',
                'specifications' => [
                    'HP 680 original tri-color ink cartridge (Box of 5 cartridges)',
                ],
            ],
            [
                'item_name' => 'Rubbing Alcohol (70%)',
                'category_name' => 'Cleaning Supplies',
                'description' => '70% Isopropyl cleaning alcohol (Box of 4 gallons).',
                'default_unit' => 'box',
                'pieces_per_box' => 4,
                'sub_unit' => 'gallon',
                'status' => 'active',
                'specifications' => [
                    '70% Isopropyl ethyl alcohol with moisturizer (Box of 4 gallons)',
                ],
            ],
            [
                'item_name' => 'Detergent Powder',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Laundry and surface cleaning detergent (Box of 24 packs @ 1kg).',
                'default_unit' => 'box',
                'pieces_per_box' => 24,
                'sub_unit' => 'pack',
                'status' => 'active',
                'specifications' => [
                    'All-purpose laundry and surface detergent, 1kg pack (Box of 24 packs)',
                ],
            ],
            [
                'item_name' => 'Trash Bag, Black XL',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Heavy-duty black plastic waste bag, XL (37" x 40") (Box of 10 rolls @ 50 pcs = 500 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'roll',
                'status' => 'active',
                'specifications' => [
                    'Heavy-duty black plastic, XL (37" x 40") (Box of 10 rolls @ 50 pcs)',
                ],
            ],
            [
                'item_name' => 'Trash Bag, Yellow Biohazard XL',
                'category_name' => 'Cleaning Supplies',
                'description' => 'Heavy-duty yellow biohazard waste bag, XL (37" x 40") (Box of 10 rolls @ 50 pcs = 500 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 10,
                'sub_unit' => 'roll',
                'status' => 'active',
                'specifications' => [
                    'Heavy-duty yellow biohazard, XL (37" x 40") (Box of 10 rolls @ 50 pcs)',
                ],
            ],
            [
                'item_name' => 'Safety Gloves',
                'category_name' => 'Maintenance Supplies',
                'description' => 'Heavy-duty industrial safety gloves (Box of 12 pairs).',
                'default_unit' => 'box',
                'pieces_per_box' => 12,
                'sub_unit' => 'pair',
                'status' => 'active',
                'specifications' => [
                    'Heavy-duty nitrile/rubber coated industrial gloves (Box of 12 pairs)',
                ],
            ],
            [
                'item_name' => 'Utility Cutter',
                'category_name' => 'Maintenance Supplies',
                'description' => 'Retractable utility cutter (Box of 12 pcs).',
                'default_unit' => 'box',
                'pieces_per_box' => 12,
                'sub_unit' => 'piece',
                'status' => 'active',
                'specifications' => [
                    'Heavy-duty retractable cutter with auto-lock blade (Box of 12 pcs)',
                ],
            ],
        ];

        foreach ($items as $itemData) {
            $categoryId = $categories->get($itemData['category_name']);
            if (! $categoryId) {
                continue;
            }

            /** @var Item $item */
            $item = Item::query()->updateOrCreate(
                ['item_name' => $itemData['item_name']],
                [
                    'item_category_id' => $categoryId,
                    'description' => $itemData['description'],
                    'default_unit' => $itemData['default_unit'],
                    'pieces_per_box' => $itemData['pieces_per_box'] ?? 1,
                    'sub_unit' => $itemData['sub_unit'] ?? 'piece',
                    'status' => $itemData['status'],
                ],
            );

            foreach ($itemData['specifications'] ?? [] as $spec) {
                ItemSpecification::query()->updateOrCreate(
                    [
                        'item_id' => $item->item_id,
                        'specification' => $spec,
                    ],
                    [
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
