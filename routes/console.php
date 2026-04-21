<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\DemoDataResetService;
use Database\Seeders\DatabaseSeeder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reseed {--force : Reset transactional demo data before reseeding.}', function () {
    if (! $this->option('force')) {
        $this->error('Refusing to reseed without --force.');

        return self::FAILURE;
    }

    $resetTables = app(DemoDataResetService::class)->resetTransactionalData();
    $this->info('Reset transactional demo tables: '.implode(', ', $resetTables));

    $exitCode = $this->call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ]);

    return $exitCode;
})->purpose('Reset transactional demo data and rebuild the longitudinal demo dataset.');
