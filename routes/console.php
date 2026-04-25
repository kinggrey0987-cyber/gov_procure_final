<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\DemoDataResetService;
use App\Services\DemoSeedAuditService;
use Database\Seeders\DatabaseSeeder;

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
