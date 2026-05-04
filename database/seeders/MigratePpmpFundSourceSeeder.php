<?php

namespace Database\Seeders;

use App\Enums\FundSource;
use App\Models\Ppmp;
use Illuminate\Database\Seeder;

class MigratePpmpFundSourceSeeder extends Seeder
{
    /**
     * Ensure existing demo/development PPMP headers have a fund source default.
     */
    public function run(): void
    {
        $ppmpCount = 0;
        $defaultsApplied = 0;

        foreach (Ppmp::all() as $ppmp) {
            if (! $ppmp->fund_source) {
                $ppmp->update(['fund_source' => FundSource::GAA_CURRENT->value]);
                $defaultsApplied++;
            }

            $ppmpCount++;
        }

        $this->command->info("Migrated {$ppmpCount} PPMPs. Applied defaults to {$defaultsApplied}.");
    }
}
