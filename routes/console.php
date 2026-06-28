<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Delivery;
use App\Models\User;
use App\Services\DemoDataResetService;
use App\Services\DemoSeedAuditService;
use App\Services\Inventory\InventoryService;
use App\Services\Procurement\ProcurementStatusSyncService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExecutiveDisclosureDemoSeeder;
use Database\Seeders\LongitudinalDemoWorkflowSeeder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reseed {--force : Reset transactional demo data before reseeding.}', function () {
    if (! $this->option('force')) {
        $this->error('Refusing to reseed without --force.');

        return self::FAILURE;
    }

    $this->call('migrate', ['--force' => true]);

    $resetTables = app(DemoDataResetService::class)->resetTransactionalData();
    $this->info('Reset transactional demo tables: '.implode(', ', $resetTables));

    $exitCode = $this->call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ]);

    if ($exitCode === self::SUCCESS) {
        $audit = app(DemoSeedAuditService::class)->auditAndFix();
        $this->info(sprintf(
            'Seed audit applied after reseed. Issues=%d Fixes=%d',
            count($audit['issues'] ?? []),
            count($audit['fixes'] ?? [])
        ));
    }

    return $exitCode;
})->purpose('Reset transactional demo data and rebuild the longitudinal demo dataset.');

Artisan::command('demo:reset-workflow', function () {
    $resetService = app(DemoDataResetService::class);

    $this->warn('Workflow-only reset starting. Auth/users/roles/offices/items/system setup will be preserved.');
    $this->info('Tables preserved: '.implode(', ', $resetService->preservedTables()));
    $this->info('Workflow deletion order: '.implode(' -> ', $resetService->workflowTablesInDeletionOrder()));

    $resetTables = $resetService->resetTransactionalData();
    $this->info('Tables reset: '.implode(', ', $resetTables));

    $this->call('db:seed', [
        '--class' => LongitudinalDemoWorkflowSeeder::class,
        '--force' => true,
    ]);

    $this->call('db:seed', [
        '--class' => ExecutiveDisclosureDemoSeeder::class,
        '--force' => true,
    ]);

    $audit = app(DemoSeedAuditService::class)->auditAndFix();
    $this->info(sprintf(
        'Seed audit applied after workflow reset. Issues=%d Fixes=%d',
        count($audit['issues'] ?? []),
        count($audit['fixes'] ?? [])
    ));

    $this->info('Workflow-only reset/reseed completed. Existing users, roles, offices, suppliers, item categories, and items were reused/preserved.');

    return self::SUCCESS;
})->purpose('Reset and reseed only workflow/transactional demo data while preserving auth, users, roles, offices, and item catalog.');

Artisan::command('demo:audit-seed', function () {
    $result = app(DemoSeedAuditService::class)->auditAndFix();

    $issues = $result['issues'] ?? [];
    $fixes = $result['fixes'] ?? [];

    $this->info(sprintf('Seed audit complete. Issues=%d Fixes=%d', count($issues), count($fixes)));

    foreach ($issues as $issue) {
        $this->line('[ISSUE] '.$issue);
    }

    foreach ($fixes as $fix) {
        $this->line('[FIX] '.$fix);
    }

    return self::SUCCESS;
})->purpose('Audit seeded transactional data and apply safe relationship/file fixes.');

Artisan::command('inventory:backfill-verified-deliveries {--delivery_id= : Backfill one verified delivery by ID.} {--dry-run : Show eligible deliveries without writing inventory movements.}', function () {
    $actor = User::query()
        ->whereHas('role', fn ($query) => $query->where('code', 'GSO_OFFICER'))
        ->orderBy('id')
        ->first();

    if (! $actor instanceof User) {
        $this->error('No GSO officer user was found. Cannot backfill inventory movements safely.');

        return self::FAILURE;
    }

    $query = Delivery::query()
        ->with(['items.purchaseOrderItem', 'purchaseOrder.supplyRequest.requester'])
        ->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
        ->whereDoesntHave('items', function ($itemQuery): void {
            $itemQuery->whereHas('purchaseOrderItem.item.inventory.movements', function ($movementQuery): void {
                $movementQuery
                    ->where('movement_type', 'DELIVERY_RECEIPT')
                    ->where('reference_type', Delivery::class)
                    ->whereColumn('reference_id', 'deliveries.delivery_id');
            });
        })
        ->orderBy('delivery_id');

    $deliveryId = $this->option('delivery_id');
    if ($deliveryId !== null && $deliveryId !== '') {
        $query->where('delivery_id', (int) $deliveryId);
    }

    $deliveries = $query->get();

    if ($deliveries->isEmpty()) {
        $this->info('No verified or partially received deliveries need inventory backfill.');

        return self::SUCCESS;
    }

    $dryRun = (bool) $this->option('dry-run');
    $inventoryService = app(InventoryService::class);

    foreach ($deliveries as $delivery) {
        $acceptedQuantity = $delivery->items->sum(fn ($item): float => max((float) $item->received_quantity - (float) $item->damaged_quantity, 0));

        $this->line(sprintf(
            '%s delivery_id=%d code=%s status=%s accepted_quantity=%.2f',
            $dryRun ? '[DRY-RUN]' : '[BACKFILL]',
            (int) $delivery->delivery_id,
            (string) $delivery->delivery_code,
            (string) $delivery->receipt_status,
            $acceptedQuantity,
        ));

        if ($dryRun) {
            continue;
        }

        $inventoryService->updateFromVerifiedDelivery($delivery, $actor);
    }

    $this->info(sprintf(
        '%s %d delivery record(s).',
        $dryRun ? 'Checked' : 'Backfilled',
        $deliveries->count(),
    ));

    return self::SUCCESS;
})->purpose('Backfill inventory movements for verified deliveries that were approved before automatic inventory sync was added.');

Artisan::command('procurement:sync-completed-statuses', function () {
    $actor = User::query()
        ->whereHas('role', fn ($query) => $query->where('code', 'GSO_OFFICER'))
        ->orderBy('id')
        ->first();

    $synced = app(ProcurementStatusSyncService::class)->syncAllReceivedSupplyRequests($actor);

    $this->info(sprintf('Checked received supply requests and synced %d completed procurement workflow(s).', $synced));

    return self::SUCCESS;
})->purpose('Sync stale PR/PO/SR statuses to final values when supply requests were already fully received.');

/*
|--------------------------------------------------------------------------
| Unclaimed Supply Check (Rev 5)
|--------------------------------------------------------------------------
| Runs daily to detect distributions that have been RELEASED for more than
| 2 days without the office confirming receipt. Creates UNCLAIMED_SUPPLY
| alerts and sends office notifications.
|
| Manual usage: php artisan supply:check-unclaimed --days=2
*/
