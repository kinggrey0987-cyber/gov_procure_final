<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\DemoDataResetService;
use App\Services\DemoSeedAuditService;
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
