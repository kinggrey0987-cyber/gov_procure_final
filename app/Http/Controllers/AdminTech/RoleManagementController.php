<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleManagementController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $dbStart = microtime(true);
        try {
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 1);
        } catch (\Throwable $e) {
            $dbLatencyMs = 0.0;
        }

        $diskFree = @disk_free_space(base_path());
        $diskTotal = @disk_total_space(base_path());
        $diskUsedPercent = ($diskTotal > 0 && $diskFree !== false)
            ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1)
            : 0.0;
        $diskFreeGb = $diskFree !== false ? round($diskFree / (1024 * 1024 * 1024), 1) : 0.0;
        $diskTotalGb = $diskTotal !== false ? round($diskTotal / (1024 * 1024 * 1024), 1) : 0.0;
        $memoryUsageMb = round(memory_get_usage(true) / (1024 * 1024), 1);

        return Inertia::render('AdminTech/Roles/Index', [
            'roles' => $roles,
            'telemetry' => [
                'db_latency_ms' => $dbLatencyMs,
                'memory_usage_mb' => $memoryUsageMb,
                'disk_used_percent' => $diskUsedPercent,
                'disk_free_gb' => $diskFreeGb,
                'disk_total_gb' => $diskTotalGb,
                'php_version' => PHP_VERSION,
                'environment' => app()->environment(),
            ],
        ]);
    }
}
