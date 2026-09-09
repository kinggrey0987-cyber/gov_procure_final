<?php

namespace App\Console\Commands;

use Database\Seeders\CleanWorkflowSeeder;
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
    protected $signature = 'data:reset-transactions 
                            {--force : Force reset without confirmation}
                            {--no-seed : Only purge workflow tables without reseeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipes workflow transactional data (preserving user accounts, offices, items, and item catalogs) and reseeds clean operational data.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('This will purge all workflow transactional data (PPMP, PRs, POs, Deliveries, Distribution, Alerts, Inventory Stock, History).');
        $this->info('User accounts, roles, offices, items, and item catalog categories will be 100% preserved.');

        if (! $this->option('force') && ! $this->confirm('Do you wish to proceed with resetting workflow data?', true)) {
            $this->info('Aborted.');
            return 0;
        }

        $tables = [
            'public_disclosure_sources',
            'public_disclosures',
            'anomalies',
            'alerts',
            'notifications',
            'audit_logs',
            'approval_history',
            'status_history',
            'claims',
            'distribution_items',
            'distribution_records',
            'delivery_verifications',
            'delivery_items',
            'deliveries',
            'inventory_movements',
            'inventory',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
            'supply_request_items',
            'supply_requests',
            'ppmp_items',
            'ppmp',
            'documents',
        ];

        $this->info('Disabling foreign key constraints...');
        Schema::disableForeignKeyConstraints();

        $this->info('Purging workflow tables...');
        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  ✓ Truncated {$table}");
                }
            } catch (\Throwable $e) {
                // Fallback to delete if truncate hits cascade restrictions
                DB::table($table)->delete();
                $this->line("  ✓ Cleared {$table}");
            }
        }

        $this->info('Re-enabling foreign key constraints...');
        Schema::enableForeignKeyConstraints();

        if (! $this->option('no-seed')) {
            $this->info('Repopulating system with clean, realistic operational workflow data...');
            $this->call('db:seed', [
                '--class' => CleanWorkflowSeeder::class,
                '--force' => true,
            ]);
        }

        $this->info('Workflow data successfully reset and repopulated!');

        return 0;
    }
}
