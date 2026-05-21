<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_units', function (Blueprint $table): void {
            $table->id('item_unit_id');
            $table->string('name', 50)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('item_units')->insert(array_map(
            fn (string $unit): array => [
                'name' => $unit,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['piece', 'box', 'pack', 'ream', 'roll', 'bottle', 'set', 'unit', 'meter', 'liter', 'kilogram'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};

