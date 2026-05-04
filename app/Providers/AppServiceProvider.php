<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Notification;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PublicDisclosure;
use App\Models\RecordArchive;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SystemBackup;
use App\Models\User;
use App\Policies\AnalyticsVisibilityPolicy;
use App\Policies\DashboardAccessPolicy;
use App\Policies\OfficeScopedAccessPolicy;
use App\Policies\PublicDisclosurePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('domain.admin-tech.access', [DashboardAccessPolicy::class, 'adminTech']);
        Gate::define('domain.office.access', [DashboardAccessPolicy::class, 'office']);
        Gate::define('domain.procurement.access', [DashboardAccessPolicy::class, 'procurement']);
        Gate::define('domain.inventory.access', [DashboardAccessPolicy::class, 'inventory']);
        Gate::define('domain.executive.access', [DashboardAccessPolicy::class, 'executive']);

        Gate::define('governance.manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('governance.manage-offices', fn (User $user): bool => $user->isAdmin());
        Gate::define('governance.view-roles', fn (User $user): bool => $user->isAdmin());
        Gate::define('governance.send-notifications', function (User $user): bool {
            return $user->hasAnyRole([
                UserRole::ADMIN,
                UserRole::PROCUREMENT_OFFICER,
                UserRole::GSO_OFFICER,
                UserRole::EXECUTIVE_OFFICER,
            ]);
        });

        Gate::define('audit.technical.view', fn (User $user): bool => $user->isAdmin());
        Gate::define('audit.business.view', fn (User $user): bool => $user->isExecutive());

        Gate::define('scope.own-office', [OfficeScopedAccessPolicy::class, 'forOfficeId']);
        Gate::define('scope.cross-office-monitoring', fn (User $user): bool => $user->isExecutive());

        Gate::define('analytics.anomalies.view', [AnalyticsVisibilityPolicy::class, 'viewExecutiveAnomalies']);
        Gate::define('analytics.forecasting.view', [AnalyticsVisibilityPolicy::class, 'viewGsoForecasting']);
        Gate::define('analytics.reorder-alerts.view', [AnalyticsVisibilityPolicy::class, 'viewGsoReorderAlerts']);
        Gate::define('analytics.delayed-delivery.view', [AnalyticsVisibilityPolicy::class, 'viewGsoDelayedDelivery']);
        Gate::define('analytics.inventory.view', [AnalyticsVisibilityPolicy::class, 'viewInventoryMonitoring']);

        Gate::define('public.disclosures.view', [PublicDisclosurePolicy::class, 'viewPublished']);
        Gate::define('public.disclosures.manage', fn (User $user): bool => $user->isAdmin());

        Gate::define('office.ppmp.view-any', fn (User $user): bool => $user->isOfficeUser());
        Gate::define('office.ppmp.create', fn (User $user): bool => $user->isOfficeUser());
        Gate::define('office.ppmp.view', function (User $user, Ppmp $ppmp): bool {
            return $user->isOfficeUser() && $ppmp->office_id === $user->office_id;
        });
        Gate::define('office.ppmp.update', function (User $user, Ppmp $ppmp): bool {
            return $user->can('office.ppmp.view', $ppmp) && in_array((string) $ppmp->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
        });
        Gate::define('office.ppmp.submit', function (User $user, Ppmp $ppmp): bool {
            return $user->can('office.ppmp.update', $ppmp);
        });

        Gate::define('office.supply-request.view-any', fn (User $user): bool => $user->isOfficeUser());
        Gate::define('office.supply-request.create', fn (User $user): bool => $user->isOfficeUser());
        Gate::define('office.supply-request.view', function (User $user, SupplyRequest $supplyRequest): bool {
            return $user->isOfficeUser() && $supplyRequest->office_id === $user->office_id;
        });
        Gate::define('office.supply-request.update', function (User $user, SupplyRequest $supplyRequest): bool {
            return $user->can('office.supply-request.view', $supplyRequest) && $supplyRequest->status === 'DRAFT';
        });
        Gate::define('office.supply-request.submit', function (User $user, SupplyRequest $supplyRequest): bool {
            return $user->can('office.supply-request.update', $supplyRequest);
        });

        Gate::define('procurement.purchase-request.view-any', fn (User $user): bool => $user->isProcurement());
        Gate::define('procurement.purchase-request.create', fn (User $user): bool => $user->isProcurement());
        Gate::define('procurement.purchase-request.view', fn (User $user, PurchaseRequest $purchaseRequest): bool => $user->isProcurement());
        Gate::define('procurement.purchase-request.update', function (User $user, PurchaseRequest $purchaseRequest): bool {
            return $user->isProcurement() && in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING', 'RETURNED_FOR_REVISION'], true);
        });
        Gate::define('procurement.purchase-request.process', function (User $user, PurchaseRequest $purchaseRequest): bool {
            return $user->isProcurement() && in_array((string) $purchaseRequest->status, ['OPEN', 'RETURNED_FOR_REVISION'], true);
        });

        Gate::define('procurement.supplier.view-any', fn (User $user): bool => $user->isProcurement());
        Gate::define('procurement.supplier.create', fn (User $user): bool => $user->isProcurement());
        Gate::define('procurement.supplier.view', fn (User $user, Supplier $supplier): bool => $user->isProcurement());
        Gate::define('procurement.supplier.update', fn (User $user, Supplier $supplier): bool => $user->isProcurement());

        Gate::define('procurement.purchase-order.view-any', fn (User $user): bool => $user->isProcurement());
        Gate::define('procurement.purchase-order.create', fn (User $user): bool => $user->isProcurement());
        Gate::define('procurement.purchase-order.view', fn (User $user, PurchaseOrder $purchaseOrder): bool => $user->isProcurement());
        Gate::define('procurement.purchase-order.update', function (User $user, PurchaseOrder $purchaseOrder): bool {
            return $user->isProcurement() && (string) $purchaseOrder->status === 'DRAFT';
        });
        Gate::define('procurement.purchase-order.place', function (User $user, PurchaseOrder $purchaseOrder): bool {
            return $user->isProcurement() && (string) $purchaseOrder->status === 'DRAFT';
        });

        Gate::define('procurement.documents.view-any', fn (User $user): bool => $user->isProcurement());

        // Delivery gates: GSO primary owner
        Gate::define('delivery.view-any', fn (User $user): bool => $user->isGsoOfficer());
        Gate::define('delivery.create', fn (User $user): bool => $user->isGsoOfficer());
        Gate::define('delivery.view', function (User $user, Delivery $delivery): bool {
            return $user->isGsoOfficer() || ($user->isProcurement());
        });
        Gate::define('delivery.update', fn (User $user, Delivery $delivery): bool => $user->isGsoOfficer());
        Gate::define('delivery-verification.create', fn (User $user, Delivery $delivery): bool => $user->isGsoOfficer());

        Gate::define('inventory.view-any', fn (User $user): bool => $user->isGsoOfficer());
        Gate::define('inventory.view', fn (User $user, Inventory $inventory): bool => $user->isGsoOfficer());
        Gate::define('inventory.manage', fn (User $user, ?Inventory $inventory = null): bool => $user->isGsoOfficer());
        Gate::define('inventory.catalog.view-any', fn (User $user): bool => $user->isGsoOfficer());
        Gate::define('inventory.catalog.manage', fn (User $user): bool => $user->isGsoOfficer());

        Gate::define('inventory-movement.view-any', fn (User $user): bool => $user->isGsoOfficer());
        Gate::define('inventory-movement.view', fn (User $user, InventoryMovement $movement): bool => $user->isGsoOfficer());

        Gate::define('distribution.view-any', fn (User $user): bool => $user->isGsoOfficer());
        Gate::define('distribution.view', function (User $user, DistributionRecord $record): bool {
            return $user->isGsoOfficer() || ($user->isOfficeUser() && (int) $record->office_id === (int) $user->office_id);
        });
        Gate::define('distribution.manage', fn (User $user, ?DistributionRecord $record = null): bool => $user->isGsoOfficer());
        Gate::define('distribution.receive', function (User $user, DistributionRecord $record): bool {
            if ($user->isGsoOfficer()) {
                return true;
            }

            return $user->isOfficeUser() && (int) $record->office_id === (int) $user->office_id;
        });

        Gate::define('claim.view-any', fn (User $user): bool => $user->isGsoOfficer() || $user->isOfficeUser());
        Gate::define('claim.create', fn (User $user): bool => $user->isOfficeUser());
        Gate::define('claim.view', function (User $user, Claim $claim): bool {
            return $user->isGsoOfficer() || ($user->isOfficeUser() && (int) $claim->office_id === (int) $user->office_id);
        });
        Gate::define('claim.update', fn (User $user, Claim $claim): bool => $user->isGsoOfficer());

        Gate::define('executive.monitoring.view', fn (User $user): bool => $user->isExecutive());
        Gate::define('executive.record-archive.view-any', fn (User $user): bool => $user->isExecutive());
        Gate::define('executive.record-archive.manage', fn (User $user): bool => $user->isExecutive());
        Gate::define('executive.record-archive.download', fn (User $user, RecordArchive $recordArchive): bool => $user->isExecutive());
        Gate::define('executive.record-archive.delete', fn (User $user, RecordArchive $recordArchive): bool => $user->isExecutive());
        Gate::define('executive.ppmp-approval.view-any', fn (User $user): bool => $user->isExecutive());
        Gate::define('executive.ppmp-approval.view', fn (User $user, Ppmp $ppmp): bool => $user->isExecutive());
        Gate::define('executive.ppmp-approval.signoff', function (User $user, Ppmp $ppmp): bool {
            return $user->isExecutive() && (string) $ppmp->status === 'SUBMITTED';
        });

        Gate::define('admin.system-backup.view-any', fn (User $user): bool => $user->isAdmin());
        Gate::define('admin.system-backup.manage', fn (User $user): bool => $user->isAdmin());
        Gate::define('admin.system-backup.download', fn (User $user, SystemBackup $systemBackup): bool => $user->isAdmin());
        Gate::define('admin.system-backup.delete', fn (User $user, SystemBackup $systemBackup): bool => $user->isAdmin());

        Gate::define('anomaly.view-any', fn (User $user): bool => $user->isExecutive());
        Gate::define('anomaly.view', fn (User $user, Anomaly $anomaly): bool => $user->isExecutive());
        Gate::define('anomaly.review', fn (User $user, Anomaly $anomaly): bool => $user->isExecutive());

        Gate::define('alert.view-any', fn (User $user): bool => $user->isExecutive() || $user->isGsoOfficer() || $user->isProcurement());
        Gate::define('alert.view', fn (User $user, Alert $alert): bool => $user->isExecutive());
        Gate::define('alert.review', fn (User $user, Alert $alert): bool => $user->isExecutive());

        Gate::define('public-disclosure.view', fn (User $user): bool => $user->isExecutive());
        Gate::define('public-disclosure.manage', fn (User $user): bool => $user->isExecutive());
        Gate::define('public-disclosure.publish', fn (User $user, PublicDisclosure $disclosure): bool => $user->isExecutive() && (string) $disclosure->status === 'DRAFT');

        Gate::define('notifications.access', fn (User $user): bool => ! $user->isPublicUser());
        Gate::define('notification.view', fn (User $user, Notification $notification): bool => ! $user->isPublicUser() && (int) $notification->user_id === (int) $user->id);
        Gate::define('notification.update', fn (User $user, Notification $notification): bool => ! $user->isPublicUser() && (int) $notification->user_id === (int) $user->id);
    }
}
