<?php

namespace Database\Seeders;

use App\Enums\FundSource;
use App\Models\Ppmp;
use Illuminate\Database\Seeder;

class MigratePpmpFundSourceSeeder extends Seeder
{
    /**
     * Run the seeder to migrate existing PPMPs with the most common fund_source from their items.
     *
     * This seeder is designed for demo/development environments where data can be modified.
     * For production data, a data migration command would be more appropriate.
     */
    public function run(): void
    {
        $ppmpCount = 0;
        $defaultsApplied = 0;

        foreach (Ppmp::all() as $ppmp) {
            $fundSources = $ppmp->items()
                ->pluck('fund_source')
                ->filter()
                ->countBy()
                ->sortDesc();

            if ($fundSources->isNotEmpty()) {
                // Use the most common fund_source from items
                $mostCommon = $fundSources->keys()->first();
                $ppmp->update(['fund_source' => $mostCommon]);
            } else {
                // Default to GAA_CURRENT if no items or all items have null fund_source
                $ppmp->update(['fund_source' => FundSource::GAA_CURRENT->value]);
                $defaultsApplied++;
            }

            $ppmpCount++;
        }

        $this->command->info("Migrated {$ppmpCount} PPMPs. Applied defaults to {$defaultsApplied}.");
    }
}
