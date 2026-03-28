<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DomainDashboardController extends Controller
{
    public function adminTech(Request $request): Response
    {
        $user = $request->user();

        $activities = AuditLog::query()
            ->technical()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'module_name', 'action_type', 'user_id', 'created_at'])
            ->map(fn (AuditLog $log): array => [
                'id' => (int) $log->id,
                'title' => ucfirst(strtolower((string) $log->action_type)).' on '.str_replace('_', ' ', (string) $log->module_name),
                'actor' => (string) ($log->user?->name ?? 'System'),
                'occurred_at' => optional($log->created_at)?->toIso8601String(),
            ])->values();

        return Inertia::render('Dashboard/AdminDashboard', [
            'summary' => [
                'total_users' => User::query()->count(),
                'office_count' => Office::query()->count(),
                'active_role_count' => Role::query()->where('is_active', true)->count(),
                'unread_notification_count' => $user
                    ? Notification::query()->forUser((int) $user->id)->unread()->count()
                    : 0,
            ],
            'activities' => $activities,
        ]);
    }

    public function office(Request $request): Response
    {
        $user = $request->user();
        $officeId = (int) $user?->office_id;

        $recentRequests = SupplyRequest::query()
            ->forOffice($officeId)
            ->withCount('items')
            ->latest('supply_request_id')
            ->limit(5)
            ->get(['supply_request_id', 'request_code', 'request_date', 'status'])
            ->map(fn (SupplyRequest $supplyRequest): array => [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'request_code' => (string) $supplyRequest->request_code,
                'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                'status' => (string) $supplyRequest->status,
                'items_count' => (int) $supplyRequest->items_count,
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

        $readyForClaimCount = DistributionRecord::query()
            ->forOffice($officeId)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->count();

        $completedRequestCount = SupplyRequest::query()
            ->forOffice($officeId)
            ->whereIn('status', ['COMPLETED', 'RELEASED'])
            ->count();

        return Inertia::render('Office/Tracking', [
            'summary' => [
                'total_requests' => SupplyRequest::query()->forOffice($officeId)->count(),
                'pending_requests' => SupplyRequest::query()->forOffice($officeId)->whereIn('status', ['DRAFT', 'SUBMITTED', 'IN_REVIEW', 'PENDING'])->count(),
                'approved_requests' => PurchaseRequest::query()->forOffice($officeId)->processing()->count(),
                'ready_for_claim' => $readyForClaimCount,
                'completed_requests' => $completedRequestCount,
            ],
            'recentRequests' => $recentRequests,
            'notificationPreview' => $notificationPreview,
        ]);
    }

    public function procurement(): Response
    {
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

        return Inertia::render('Procurement/Tracking', [
            'summary' => [
                'pending_requests' => PurchaseRequest::query()->open()->count(),
                'approved_requests' => PurchaseRequest::query()->processing()->count(),
                'ongoing_procurement' => PurchaseOrder::query()->placed()->count(),
                'completed_procurements' => PurchaseOrder::query()->where('status', 'COMPLETED')->count(),
            ],
            'recentPurchaseRequests' => $recentPurchaseRequests->map(fn (PurchaseRequest $request): array => [
                'purchase_request_id' => (int) $request->purchase_request_id,
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
        ]);
    }

    public function inventory(): Response
    {
        return Inertia::render('Inventory/Tracking', [
            'summary' => [
                'inventory_count' => Inventory::query()->count(),
                'low_stock_count' => Inventory::query()->lowStock()->count(),
                'movement_count' => InventoryMovement::query()->count(),
                'released_distribution_count' => DistributionRecord::query()->released()->count(),
                'received_distribution_count' => DistributionRecord::query()->received()->count(),
                'open_claim_count' => Claim::query()->open()->count(),
            ],
        ]);
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
}
