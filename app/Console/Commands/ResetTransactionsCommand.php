<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:reset-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipes transactional data (leaving users intact) and reseeds standard demo workflow.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('This will wipe all transactional data (PPMP, PR, PO, Inventory, Items, Alerts, etc) and re-seed the dummy data.');
        
        if (! $this->confirm('Do you wish to continue?', true)) {
            $this->info('Aborted.');
            return;
        }

        $tables = [
            'anomalies',
            'alerts',
            'claims',
            'delivery_verifications',
            'delivery_items',
            'deliveries',
            'distribution_items',
            'distribution_records',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
            'supply_request_items',
            'supply_requests',
            'ppmp_items',
            'ppmp',
            'inventory',
            'items',
            'item_categories',
            'status_history',
            'approval_history',
            'documents',
        ];

        $this->info('Disabling foreign key constraints...');
        Schema::disableForeignKeyConstraints();

        $this->info('Truncating tables...');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->line("Truncated {$table}");
        }

        $this->info('Re-enabling foreign key constraints...');
        Schema::enableForeignKeyConstraints();

        $this->info('Running seeders...');
        $this->call('db:seed', ['--class' => 'ItemCatalogSeeder']);
        
        $this->info('Running Demo Workflow seeders...');
        $this->call('db:seed', ['--class' => 'LongitudinalDemoWorkflowSeeder']);
        $this->call('db:seed', ['--class' => 'ExecutiveDisclosureDemoSeeder']);
        $this->call('db:seed', ['--class' => 'GovernanceOpsDemoSeeder']);

        $this->info('Transactions reset and seeded successfully.');
    }
}
