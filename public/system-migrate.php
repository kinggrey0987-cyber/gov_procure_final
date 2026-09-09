<?php

declare(strict_types=1);

// Security Key check
$secretKey = 'deploy-secret-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $secretKey) {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><title>System Migrate - Unauthorized</title></head><body style='background:#121212;color:#fff;font-family:monospace;padding:30px;'>";
    echo "<div style='background:#1e1e1e;color:#ff6b6b;padding:24px;font-size:14px;border-radius:8px;max-width:800px;margin:0 auto;border:1px solid #ff4444;'>";
    echo "<h2 style='margin-top:0;color:#ff6b6b;'>⚠️ 403 Unauthorized</h2>";
    echo "<p>Please provide the secret key in the URL parameter.</p>";
    echo "<p><strong>Quick Links:</strong></p>";
    echo "<ul>";
    echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=reset-transactions' style='color:#f6c326;font-weight:bold;'>⭐ Reset & Repopulate Defense Workflow (2024-2026): ?action=reset-transactions</a></li>";
    echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=migrate' style='color:#4af626;'>Run Migrations: ?key=deploy-secret-2026&action=migrate</a></li>";
    echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=clear-cache' style='color:#4af626;'>Clear Cache: ?key=deploy-secret-2026&action=clear-cache</a></li>";
    echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=status' style='color:#4af626;'>Migration Status: ?key=deploy-secret-2026&action=status</a></li>";
    echo "</ul>";
    echo "</div></body></html>";
    exit;
}

@set_time_limit(600);
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$action = $_GET['action'] ?? 'status';
$output = '';
$isSuccess = true;

try {
    switch ($action) {
        case 'reset-transactions':
        case 'reset-workflow':
        case 'seed-clean':
        case 'seed-defense':
            \Illuminate\Support\Facades\Artisan::call('data:reset-transactions', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'migrate':
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'seed':
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'seed-multiyear':
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\MultiYearHistoricalProcurementSeeder',
                '--force' => true,
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'seed-catalog':
        case 'seed-items':
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\ItemCatalogSeeder',
                '--force' => true,
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'refresh-disclosures':
            \Illuminate\Support\Facades\Artisan::call('disclosures:refresh-packages');
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'clear-cache':
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'storage-link':
            \Illuminate\Support\Facades\Artisan::call('storage:link');
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        default:
            \Illuminate\Support\Facades\Artisan::call('migrate:status');
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
    }
} catch (\Throwable $e) {
    $isSuccess = false;
    $output = "Exception: " . $e->getMessage() . "\n\nFile: " . $e->getFile() . ":" . $e->getLine() . "\n\nTrace:\n" . $e->getTraceAsString();
}

$color = $isSuccess ? '#4af626' : '#ff6b6b';
$title = $isSuccess ? 'Action Succeeded' : 'Action Failed';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>System Migration - {$action}</title></head><body style='background:#121212;color:#fff;font-family:monospace;padding:30px;'>";
echo "<div style='background:#1e1e1e;color:{$color};padding:24px;font-size:14px;border-radius:8px;max-width:900px;margin:0 auto;border:1px solid #333;'>";
echo "<h2 style='margin-top:0;color:{$color};'>{$title}: " . htmlspecialchars($action) . "</h2>";
echo "<pre style='white-space:pre-wrap;word-break:break-all;font-size:13px;line-height:1.6;'>" . htmlspecialchars($output) . "</pre>";
echo "<hr style='border-color:#333;margin:20px 0;'>";
echo "<p style='color:#aaa;'><strong>Actions:</strong></p>";
echo "<ul style='color:#aaa;'>";
echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=reset-transactions' style='color:#f6c326;font-weight:bold;'>⭐ Reset & Repopulate Defense Workflow (2024-2026): ?action=reset-transactions</a></li>";
echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=migrate' style='color:#4af626;'>Run Migrations (migrate)</a></li>";
echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=seed-multiyear' style='color:#4af626;'>Seed 3-Year Historical Data (seed-multiyear)</a></li>";
echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=refresh-disclosures' style='color:#4af626;'>Refresh All Disclosure Packages (refresh-disclosures)</a></li>";
echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=clear-cache' style='color:#4af626;'>Clear All Caches (clear-cache)</a></li>";
echo "<li><a href='system-migrate.php?key=deploy-secret-2026&action=status' style='color:#4af626;'>Check Migration Status (status)</a></li>";
echo "</ul>";
echo "</div></body></html>";
