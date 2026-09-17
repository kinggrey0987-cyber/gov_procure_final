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
            ->latest('created_at');

        // Paginate with 5 records per page. Keep the paginator instance for
        // metadata and read activity rows from its collection; paginator
        // only() may omit the data key in some Laravel contexts.
        $activitiesPaginator = $query->paginate(perPage: 5, page: $filters['page'] ?? 1);

        $activities = $activitiesPaginator->getCollection()
            ->map(fn (AuditLog $log): array => [
                'id' => (int) $log->id,
                'title' => $this->activityTitle($log),
                'actor' => (string) ($log->user?->name ?? 'System'),
                'occurred_at' => optional($log->created_at)?->toIso8601String(),
            ])->values();

        $response = Inertia::render('Dashboard/AdminDashboard', [
            'summary' => [
                'total_users' => User::query()->count(),
                'office_count' => Office::query()->count(),
                'active_role_count' => Role::query()->where('is_active', true)->count(),
                'unread_notification_count' => $user
                    ? Notification::query()->forUser((int) $user->id)->unread()->count()
                    : 0,
            ],
            'activities' => $activities,
            'pagination' => [
                'current_page' => $activitiesPaginator->currentPage(),
                'last_page' => $activitiesPaginator->lastPage(),
                'prev_page_url' => $activitiesPaginator->previousPageUrl(),
                'next_page_url' => $activitiesPaginator->nextPageUrl(),
                'total' => $activitiesPaginator->total(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? null,
                'actor' => $filters['actor'] ?? null,
            ],
        ]);

        $this->logIfSlow('admin-tech', $startedAt, (int) ($user?->id ?? 0));

        return $response;
    }

    public function office(Request $request): Response
    {
        $startedAt = microtime(true);
        $user = $request->user();
        $officeId = (int) $user?->office_id;

        $recentRequests = SupplyRequest::query()
            ->forOffice($officeId)
            ->withCount('items')
            ->latest('supply_request_id')
            ->limit(15)
            ->get(['supply_request_id', 'request_code', 'request_date', 'status'])
            ->map(fn (SupplyRequest $supplyRequest): array => [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'request_code' => (string) $supplyRequest->request_code,
                'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                'status' => (string) $supplyRequest->status,
                'items_count' => (int) $supplyRequest->items_count,
            ])
            ->values();

        $releasedSuppliesPreview = DistributionRecord::query()
            ->forOffice($officeId)
            ->with(['supplyRequest', 'items.item'])
            ->whereIn('status', DashboardStatusGroups::officeReadyForClaimDistributionStatuses())
            ->latest('release_date')
            ->latest('distribution_record_id')
            ->limit(5)
            ->get()
            ->map(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'distribution_code' => (string) $record->distribution_code,
                'request_code' => (string) ($record->supplyRequest?->request_code ?? 'Unlinked request'),
                'release_date' => optional($record->release_date)?->toDateString(),
                'status' => (string) $record->status,
                'items_count' => (int) $record->items->count(),
                'items_summary' => $record->items
                    ->take(2)
                    ->map(fn ($item): string => (string) ($item->item?->item_name ?? $item->item_name_snapshot))
                    ->implode(', '),
            ])
            ->values();

        $notificationPreview = $user
            ? Notification::query()
                ->forUser((int) $user->id)
                ->latest('created_at')
                ->limit(3)
                ->get(['notification_id', 'title', 'message', 'is_read', 'created_at'])
                ->map(fn (Notification $notification): array => [
                    'notification_id' => (int) $notification->notification_id,
                    'title' => (string) $notification->title,
                    'message' => (string) $notification->message,
                    'is_read' => (bool) $notification->is_read,
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                ])
                ->values()
            : collect();

        $totalRequestsCount = SupplyRequest::query()->forOffice($officeId)->count();

        $pendingRequestsCount = SupplyRequest::query()
            ->forOffice($officeId)
            ->whereIn('status', DashboardStatusGroups::officePendingSupplyRequestStatuses())
            ->whereDoesntHave('purchaseRequests')
            ->whereDoesntHave('distributionRecords')
            ->count();

        $readyForClaimCount = (int) DistributionRecord::query()
            ->forOffice($officeId)
            ->whereIn('status', DashboardStatusGroups::officeReadyForClaimDistributionStatuses())
            ->distinct('supply_request_id')
            ->count('supply_request_id');

        $completedRequestCount = (int) DistributionRecord::query()
            ->forOffice($officeId)
            ->whereIn('status', DashboardStatusGroups::officeCompletedDistributionStatuses())
            ->distinct('supply_request_id')
            ->count('supply_request_id');

        $processedRequestsCount = max($totalRequestsCount - $pendingRequestsCount, 0);

        $response = Inertia::render('Office/Tracking', [
            'summary' => [
                'total_requests' => $totalRequestsCount,
                'pending_requests' => $pendingRequestsCount,
                'processed_requests' => $processedRequestsCount,
                'ready_for_claim' => $readyForClaimCount,
                'completed_requests' => $completedRequestCount,
            ],
            'recentRequests' => $recentRequests,
            'releasedSuppliesPreview' => $releasedSuppliesPreview,
            'notificationPreview' => $notificationPreview,
            'dashboardAlerts' => $this->fetchDashboardAlerts(officeId: $officeId),
        ]);

        $this->logIfSlow('office', $startedAt, (int) ($user?->id ?? 0));

        return $response;
    }

    public function procurement(): Response
    {
        $startedAt = microtime(true);
        $recentPurchaseRequests = PurchaseRequest::query()
            ->with(['office'])
            ->latest('purchase_request_id')
            ->limit(5)
            ->get();

        $recentPurchaseOrders = PurchaseOrder::query()
            ->with(['supplier'])
            ->latest('purchase_order_id')
            ->limit(5)
            ->get();

        $response = Inertia::render('Procurement/Tracking', [
            'summary' => [
                'pending_requests' => PurchaseRequest::query()
                    ->whereIn('status', DashboardStatusGroups::procurementPendingPurchaseRequestStatuses())
                    ->count(),
                'processing_requests' => PurchaseRequest::query()
                    ->whereIn('status', DashboardStatusGroups::procurementInProgressPurchaseRequestStatuses())
                    ->count(),
                'ongoing_procurement' => PurchaseOrder::query()
                    ->whereIn('status', DashboardStatusGroups::procurementOngoingPurchaseOrderStatuses())
                    ->count(),
                'completed_procurements' => PurchaseOrder::query()
                    ->whereIn('status', DashboardStatusGroups::procurementCompletedPurchaseOrderStatuses())
                    ->count(),
                'total_purchase_order_amount' => (float) PurchaseOrder::query()->sum('total_amount'),
                'draft_purchase_orders' => PurchaseOrder::query()->where('status', 'DRAFT')->count(),
                'placed_purchase_orders' => PurchaseOrder::query()->where('status', 'PLACED')->count(),
                'active_supplier_count' => \App\Models\Supplier::query()->where('status', 'ACTIVE')->count(),
            ],
            'recentPurchaseRequests' => $recentPurchaseRequests->map(fn (PurchaseRequest $request): array => [
                'purchase_request_id' => (int) $request->purchase_request_id,
                'pr_number' => (string) $request->pr_number,
                'office_name' => (string) ($request->office?->name ?? 'Unknown Office'),
                'status' => (string) $request->status,
                'created_at' => optional($request->created_at)?->toIso8601String(),
            ])->values(),
            'recentPurchaseOrders' => $recentPurchaseOrders->map(fn (PurchaseOrder $order): array => [
                'purchase_order_id' => (int) $order->purchase_order_id,
                'po_number' => (string) $order->po_number,
                'supplier_name' => (string) ($order->supplier?->supplier_name ?? 'No Supplier'),
                'status' => (string) $order->status,
                'total_amount' => (float) $order->total_amount,
                'created_at' => optional($order->created_at)?->toIso8601String(),
            ])->values(),
            'dashboardAlerts' => $this->fetchDashboardAlerts(alertTypes: ['ANOMALY_DETECTED', 'PRICE_DEVIATION']),
        ]);

        $this->logIfSlow('procurement', $startedAt, (int) (auth()->user()?->id ?? 0));

        return $response;
    }

    public function inventory(): Response
    {
        $startedAt = microtime(true);

        $response = Inertia::render('Inventory/Tracking', [
            'summary' => [
                'inventory_count' => Inventory::query()->count(),
                'low_stock_count' => Inventory::query()->lowStock()->count(),
                'out_of_stock_count' => Inventory::query()->outOfStock()->count(),
                'total_available_stock' => (float) Inventory::query()->sum('available_stock'),
                'reserved_quantity' => (float) Inventory::query()
                    ->selectRaw('SUM(CASE WHEN stock_on_hand > available_stock THEN stock_on_hand - available_stock ELSE 0 END) as computed_reserved_quantity')
                    ->value('computed_reserved_quantity'),
                'movement_count' => InventoryMovement::query()->count(),
                'released_distribution_count' => DistributionRecord::query()->released()->count(),
                'received_distribution_count' => DistributionRecord::query()->received()->count(),
                'pending_delivery_count' => \App\Models\Delivery::query()->where('receipt_status', 'PENDING')->count(),
                'open_claim_count' => Claim::query()
                    ->whereIn('status', DashboardStatusGroups::claimActiveStatuses())
                    ->count(),
            ],
            'dashboardAlerts' => $this->fetchDashboardAlerts(alertTypes: ['UNCLAIMED_SUPPLY', 'LOW_STOCK', 'DELIVERY_DISCREPANCY', 'ANOMALY_DETECTED']),
        ]);

        $this->logIfSlow('inventory', $startedAt, (int) (auth()->user()?->id ?? 0));

        return $response;
    }

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

    /**
     * Fetch active dashboard alerts, optionally scoped to an office or specific alert types.
     *
     * @param  int|null  $officeId
     * @param  string[]  $alertTypes
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function fetchDashboardAlerts(?int $officeId = null, array $alertTypes = []): \Illuminate\Support\Collection
    {
        $query = Alert::query()
            ->whereIn('status', ['ACTIVE', 'PENDING'])
            ->with('office:id,name')
            ->latest('detected_at')
            ->limit(10);

        if ($officeId !== null) {
            $query->forOffice($officeId);
        }

        if (count($alertTypes) > 0) {
            $query->whereIn('alert_type', $alertTypes);
        }

        return $query->get()->map(fn (Alert $alert): array => [
            'alerts_id' => (int) $alert->alerts_id,
            'alert_type' => (string) $alert->alert_type,
            'severity' => (string) $alert->severity,
            'alert_message' => (string) $alert->alert_message,
            'detected_at' => optional($alert->detected_at)?->toIso8601String(),
            'office_name' => (string) ($alert->office?->name ?? ''),
            'supply_request_id' => $alert->supply_request_id ? (int) $alert->supply_request_id : null,
            'purchase_order_id' => $alert->purchase_order_id ? (int) $alert->purchase_order_id : null,
        ])->values();
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
