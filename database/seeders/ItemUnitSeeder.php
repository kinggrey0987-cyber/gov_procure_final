<?php

namespace Database\Seeders;

use App\Models\ItemUnit;
use Illuminate\Database\Seeder;

class ItemUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['piece', 'box', 'pack', 'ream', 'roll', 'bottle', 'set', 'unit', 'meter', 'liter', 'kilogram'] as $unit) {
            ItemUnit::query()->updateOrCreate(
                ['name' => $unit],
                ['status' => 'active'],
            );
        }
    }
}

