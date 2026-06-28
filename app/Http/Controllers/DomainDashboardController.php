<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Ppmp;
use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Notification;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Support\DashboardStatusGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DomainDashboardController extends Controller
{
    public function adminTech(Request $request): Response
    {
        $startedAt = microtime(true);
        $user = $request->user();

        // Validate and get filter parameters
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Build query with optional filters. Authentication activity is not
        // role-limited here so two-factor events from office users and other
        // internal users remain visible to administrators.
        $query = AuditLog::query()
            ->technical()
            ->with('user:id,name')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->whereLikeInsensitive('module_name', $search)
                        ->orWhereLikeInsensitive('action_type', $search);
                });
            })
            ->when($filters['actor'] ?? null, function ($q, $actor) {
                $q->whereHas('user', fn ($userQuery) => $userQuery->whereLikeInsensitive('name', $actor));
            })

    public function executive(): Response
    {
        return Inertia::render('Domain/Placeholder', [
            'title' => 'Executive Monitoring Workspace',
            'description' => 'EXEC_OFFICER executive analytics and business audit visibility scaffolding.',
        ]);
    }

    public function public(): Response
    {
        return Inertia::render('Domain/Placeholder', [
            'title' => 'Public Transparency Portal',
            'description' => 'Approved and published disclosures only.',
        ]);
    }

    private function logIfSlow(string $dashboard, float $startedAt, int $userId): void
    {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($durationMs < 1500) {
            return;
        }

        Log::warning('dashboard.load_slow', [
            'dashboard' => $dashboard,
            'user_id' => $userId,
            'duration_ms' => $durationMs,
        ]);
    }

    private function activityTitle(AuditLog $log): string
    {
        $actionType = (string) $log->action_type;

        return match ($actionType) {
            '2FA_CODE_ISSUED' => 'Two-factor sign-in code sent',
            '2FA_CODE_RESENT' => 'Two-factor sign-in code resent',
            '2FA_VERIFY_SUCCESS' => 'Two-factor sign-in completed',
            '2FA_CODE_DELIVERY_FAILED' => 'Two-factor sign-in code could not be sent',
            '2FA_VERIFY_FAILED_NO_CHALLENGE' => 'Two-factor sign-in attempt had no active code',
            '2FA_VERIFY_FAILED_EXPIRED' => 'Two-factor sign-in code expired',
            '2FA_VERIFY_FAILED_LOCKED' => 'Two-factor sign-in was locked after too many attempts',
            '2FA_VERIFY_FAILED_INVALID' => 'Incorrect two-factor sign-in code entered',
            default => $this->humanizeAuditAction($actionType).' in '.$this->humanizeAuditModule((string) $log->module_name),
        };
    }

    private function humanizeAuditAction(string $actionType): string
    {
        return str($actionType)
            ->replace(['_', '.'], ' ')
            ->lower()
            ->headline()
            ->toString();
    }

    private function humanizeAuditModule(string $moduleName): string
    {
        return str($moduleName)
            ->replace(['_', '.'], ' ')
            ->headline()
            ->toString();
    }
}
