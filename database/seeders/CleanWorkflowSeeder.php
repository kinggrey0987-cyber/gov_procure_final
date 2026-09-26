<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CleanWorkflowSeeder extends Seeder
{
    /**
     * Seeds clean, realistic operational workflow data for GovSupply Connect.
     * Delegates to TransactionalDataRefreshSeeder for category-driven workflow consistency.
     */
    public function run(): void
    {
        $this->call(TransactionalDataRefreshSeeder::class);
    }
}
