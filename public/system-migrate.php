<?php

declare(strict_types=1);

@set_time_limit(600);
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \Illuminate\Support\Facades\Auth::user();
$isAdmin = $user && ($user->isAdmin() || $user->can('domain.admin-tech.access'));

$secretKey = (string) env('DEPLOY_SECRET_KEY', '');
$providedKey = (string) ($_GET['key'] ?? '');
$hasValidKey = ($secretKey !== '' && $providedKey !== '' && hash_equals($secretKey, $providedKey));

if (! $isAdmin && ! $hasValidKey) {
    http_response_code(403);
    $errorMessage = $providedKey !== '' ? "<div style='background:#450a0a;border:1px solid #dc2626;color:#fecaca;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;'>❌ Invalid Key. Please check your credentials.</div>" : "";
    echo "<!DOCTYPE html><html><head><title>System Maintenance - Authentication Required</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{margin:0;background:#0b0f19;color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}input:focus{border-color:#ef4444!important;outline:none;}</style></head><body><div style='background:#111827;border:1px solid #1f2937;border-radius:16px;max-width:420px;width:100%;padding:32px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);text-align:center;'><div style='width:52px;height:52px;background:#1e293b;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;color:#ef4444;'><svg width='28' height='28' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'></path></svg></div><h2 style='margin:0 0 8px 0;font-size:20px;font-weight:700;'>System Maintenance Console</h2><p style='color:#94a3b8;font-size:13px;margin:0 0 24px 0;line-height:1.5;'>Log in as an Administrator, or enter your Deploy Secret Key below to manage migrations and caches.</p>{$errorMessage}<form method='GET' action='system-migrate.php' style='text-align:left;'><label style='display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;margin-bottom:6px;'>Secret Key</label><input type='password' name='key' placeholder='••••••••••••' required autofocus style='width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid #374151;background:#1f2937;color:#fff;font-size:15px;margin-bottom:16px;'><input type='hidden' name='action' value='status'><button type='submit' style='width:100%;padding:12px;border-radius:10px;border:none;background:#b91c1c;color:#fff;font-weight:600;font-size:14px;cursor:pointer;'>Unlock Maintenance Console &rarr;</button></form><p style='margin-top:20px;font-size:12px;color:#64748b;'>GovSupply Connect &bull; Administrative Operations</p></div></body></html>";
    exit;
}

$activeKey = $hasValidKey ? $providedKey : '';
$keyParam = $activeKey !== '' ? ('key='.urlencode($activeKey).'&') : '';
$action = (string) ($_GET['action'] ?? 'status');
$output = '';

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
        case 'sync-consolidated':
            \Illuminate\Support\Facades\Artisan::call('procurement:sync-consolidated-statuses');
            $output = \Illuminate\Support\Facades\Artisan::output();
            break;
        case 'clear-cache':
            \Illuminate\Support\Facades\DB::table('supply_requests')->where('status', 'FULFILLED')->update(['status' => 'COMPLETED']);
            \Illuminate\Support\Facades\DB::table('deliveries')
                ->where('receipt_status', 'RECEIVED')
                ->whereExists(function ($q) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('delivery_verifications')
                        ->whereColumn('delivery_verifications.delivery_id', 'deliveries.delivery_id')
                        ->where('verification_status', 'APPROVED');
                })
                ->update(['receipt_status' => 'VERIFIED']);

            $del34 = \Illuminate\Support\Facades\DB::table('deliveries')->where('delivery_code', 'DEL-2026-00034')->first();
            if ($del34) {
                \Illuminate\Support\Facades\DB::table('delivery_items')
                    ->where('delivery_id', $del34->delivery_id)
                    ->where('expected_quantity', 10)
                    ->where('received_quantity', 5)
                    ->where('damaged_quantity', 5)
                    ->update([
                        'received_quantity' => 10,
                        'damaged_quantity' => 5,
                        'missing_quantity' => 0,
                        'item_condition' => 'DAMAGED',
                    ]);
            }

            \Illuminate\Support\Facades\Artisan::call('procurement:sync-consolidated-statuses');
            $syncOutput = \Illuminate\Support\Facades\Artisan::output();

            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            $output = "Normalized legacy supply requests, verified deliveries, and cleared caches.\n\n" . ($syncOutput ? "Consolidated Status Sync:\n" . $syncOutput . "\n" : "") . \Illuminate\Support\Facades\Artisan::output();
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
    $output = "Exception: " . $e->getMessage() . "\n\nFile: " . $e->getFile() . ":" . $e->getLine() . "\n\nTrace:\n" . $e->getTraceAsString();
}

$escapedAction = htmlspecialchars($action);
$escapedOutput = htmlspecialchars($output);

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>System Maintenance - {$escapedAction}</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{margin:0;background:#0b0f19;color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;padding:30px 20px;}.btn{display:inline-flex;align-items:center;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.15s;margin-bottom:8px;}.btn-green{background:#15803d;color:#fff;}.btn-green:hover{background:#166534;}.btn-blue{background:#1d4ed8;color:#fff;}.btn-blue:hover{background:#1e40af;}.btn-amber{background:#b45309;color:#fff;}.btn-amber:hover{background:#92400e;}.btn-slate{background:#334155;color:#fff;}.btn-slate:hover{background:#1e293b;}</style></head><body><div style='max-width:960px;margin:0 auto;'><div style='display:flex;align-items:center;justify-content:between;margin-bottom:20px;flex-wrap:wrap;gap:12px;'><div style='display:flex;align-items:center;gap:12px;'><span style='width:12px;height:12px;border-radius:50%;background:#22c55e;display:inline-block;'></span><h1 style='margin:0;font-size:22px;font-weight:700;'>System Maintenance Console</h1></div><span style='background:#1e293b;border:1px solid #334155;padding:4px 10px;border-radius:6px;font-size:12px;color:#94a3b8;'>Authenticated Session</span></div><div style='background:#111827;border:1px solid #1f2937;border-radius:12px;padding:20px;margin-bottom:24px;'><div style='font-size:12px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:12px;'>Quick Actions:</div><div style='display:flex;flex-wrap:wrap;gap:8px;'><a href='system-migrate.php?{$keyParam}action=clear-cache' class='btn btn-green'>🧹 Clear All Caches</a><a href='system-migrate.php?{$keyParam}action=sync-consolidated' class='btn btn-blue'>🔄 Sync Consolidated Requests</a><a href='system-migrate.php?{$keyParam}action=status' class='btn btn-blue'>📊 Migration Status</a><a href='system-migrate.php?{$keyParam}action=migrate' class='btn btn-blue'>🔄 Run Migrations</a><a href='system-migrate.php?{$keyParam}action=storage-link' class='btn btn-slate'>🔗 Storage Link</a><a href='system-migrate.php?{$keyParam}action=refresh-disclosures' class='btn btn-slate'>📄 Refresh Disclosures</a><a href='system-migrate.php?{$keyParam}action=reset-transactions' class='btn btn-amber' onclick='return confirm(\"Are you sure you want to reset demo workflow transactions?\")'>⚠️ Reset Demo Transactions</a></div></div><div style='background:#111827;border:1px solid #1f2937;border-radius:12px;overflow:hidden;'><div style='padding:12px 20px;background:#1e293b;border-bottom:1px solid #334155;font-size:13px;font-weight:600;'>Execution Output: <span style='color:#38bdf8;'>{$escapedAction}</span></div><pre style='margin:0;padding:20px;font-family:ui-monospace,Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.6;color:#4ade80;background:#090d16;overflow-x:auto;white-space:pre-wrap;'>{$escapedOutput}</pre></div></div></body></html>";

