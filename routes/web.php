<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Governance\AuditLogController;
use App\Http\Controllers\AdminTech\OfficeManagementController;
use App\Http\Controllers\AdminTech\RoleManagementController;
use App\Http\Controllers\AdminTech\UserManagementController;
use App\Http\Controllers\DomainDashboardController;
use App\Http\Controllers\Office\PpmpController as OfficePpmpController;
use App\Http\Controllers\Office\PpmpItemController as OfficePpmpItemController;
use App\Http\Controllers\Office\SupplyRequestController as OfficeSupplyRequestController;
use App\Http\Controllers\Office\SupplyRequestItemController as OfficeSupplyRequestItemController;
use App\Http\Controllers\Procurement\PurchaseOrderController as ProcurementPurchaseOrderController;
use App\Http\Controllers\Procurement\ProcurementDocumentController;
use App\Http\Controllers\Procurement\PurchaseOrderItemController as ProcurementPurchaseOrderItemController;
use App\Http\Controllers\Procurement\PurchaseRequestController as ProcurementPurchaseRequestController;
use App\Http\Controllers\Procurement\PurchaseRequestItemController as ProcurementPurchaseRequestItemController;
use App\Http\Controllers\Procurement\SupplierController as ProcurementSupplierController;
use App\Http\Controllers\Inventory\ClaimController;
use App\Http\Controllers\Inventory\DeliveryController;
use App\Http\Controllers\Inventory\DeliveryItemController;
use App\Http\Controllers\Inventory\DeliveryVerificationController;
use App\Http\Controllers\Inventory\DistributionItemController;
use App\Http\Controllers\Inventory\DistributionRecordController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\ItemCatalogController;
use App\Http\Controllers\Inventory\ItemCategoryController;
use App\Http\Controllers\Inventory\RequestWorkflowController;
use App\Http\Controllers\Executive\AlertController as ExecutiveAlertController;
use App\Http\Controllers\Executive\AnomalyController;
use App\Http\Controllers\Executive\ExecutiveMonitoringController;
use App\Http\Controllers\Executive\PpmpApprovalController;
use App\Http\Controllers\Executive\PublicDisclosureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Public\PublicDisclosurePortalController;
use App\Http\Controllers\ProfileController;
use App\Services\Public\PublicLandingService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $landingData = app(PublicLandingService::class)->summary();

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'summaryStats' => $landingData['summaryStats'],
        'latestDisclosures' => $landingData['latestDisclosures'],
        'monthlyDisclosures' => $landingData['monthlyDisclosures'],
        'typeBreakdown' => $landingData['typeBreakdown'],
        'publicReports' => $landingData['publicReports'],
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'twofactor'])
    ->name('dashboard');

Route::middleware(['auth', 'twofactor'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/{related_module}/{related_reference_id}', [DocumentController::class, 'index'])
            ->whereIn('related_module', ['ppmp', 'supply_requests', 'purchase_requests', 'purchase_orders', 'deliveries', 'claims', 'public_disclosures'])
            ->whereNumber('related_reference_id')
            ->name('index');

        Route::post('/{related_module}/{related_reference_id}', [DocumentController::class, 'store'])
            ->whereIn('related_module', ['ppmp', 'supply_requests', 'purchase_requests', 'purchase_orders', 'deliveries', 'claims', 'public_disclosures'])
            ->whereNumber('related_reference_id')
            ->name('store');

        Route::get('/file/{document}', [DocumentController::class, 'show'])
            ->name('show');

        Route::delete('/file/{document}', [DocumentController::class, 'destroy'])
            ->name('destroy');
    });

    Route::prefix('notifications')->name('notifications.')->middleware('can:notifications.access')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/{notification}', [NotificationController::class, 'show'])
            ->middleware('can:notification.view,notification')
            ->name('show');
        Route::put('/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->middleware('can:notification.update,notification')
            ->name('mark-read');
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    });

    Route::prefix('admin-tech')
        ->name('admin-tech.')
        ->middleware(['verified', 'domain:admin-tech'])
        ->group(function () {
            Route::get('/', [DomainDashboardController::class, 'adminTech'])
                ->name('dashboard');

            Route::get('/users', [UserManagementController::class, 'index'])
                ->middleware('can:governance.manage-users')
                ->name('users.index');

            Route::get('/users/create', [UserManagementController::class, 'create'])
                ->middleware('can:governance.manage-users')
                ->name('users.create');

            Route::post('/users', [UserManagementController::class, 'store'])
                ->middleware('can:governance.manage-users')
                ->name('users.store');

            Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])
                ->middleware('can:governance.manage-users')
                ->name('users.edit');

            Route::put('/users/{user}', [UserManagementController::class, 'update'])
                ->middleware('can:governance.manage-users')
                ->name('users.update');

            Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
                ->middleware('can:governance.manage-users')
                ->name('users.destroy');

            Route::get('/offices', [OfficeManagementController::class, 'index'])
                ->middleware('can:governance.manage-offices')
                ->name('offices.index');

            Route::get('/offices/create', [OfficeManagementController::class, 'create'])
                ->middleware('can:governance.manage-offices')
                ->name('offices.create');

            Route::post('/offices', [OfficeManagementController::class, 'store'])
                ->middleware('can:governance.manage-offices')
                ->name('offices.store');

            Route::get('/offices/{office}/edit', [OfficeManagementController::class, 'edit'])
                ->middleware('can:governance.manage-offices')
                ->name('offices.edit');

            Route::put('/offices/{office}', [OfficeManagementController::class, 'update'])
                ->middleware('can:governance.manage-offices')
                ->name('offices.update');

            Route::delete('/offices/{office}', [OfficeManagementController::class, 'destroy'])
                ->middleware('can:governance.manage-offices')
                ->name('offices.destroy');

            Route::get('/roles', [RoleManagementController::class, 'index'])
                ->middleware('can:governance.view-roles')
                ->name('roles.index');

            Route::get('/audit/technical', [AuditLogController::class, 'technical'])
                ->middleware('can:audit.technical.view')
                ->name('audit.technical');
        });

    Route::prefix('office')
        ->name('office.')
        ->middleware(['verified', 'domain:office'])
        ->group(function () {
            Route::get('/', [DomainDashboardController::class, 'office'])
                ->name('dashboard');

            Route::get('/tracking', [DomainDashboardController::class, 'office'])
                ->name('tracking');

            Route::get('/supplies-ready-for-claim', [OfficeSupplyRequestController::class, 'suppliesReadyForClaim'])
                ->middleware('can:office.supply-request.view-any')
                ->name('supplies-ready.index');

            Route::prefix('ppmps')->name('ppmp.')->group(function () {
                Route::get('/', [OfficePpmpController::class, 'index'])
                    ->middleware('can:office.ppmp.view-any')
                    ->name('index');
                Route::get('/create', [OfficePpmpController::class, 'create'])
                    ->middleware('can:office.ppmp.create')
                    ->name('create');
                Route::post('/', [OfficePpmpController::class, 'store'])
                    ->middleware('can:office.ppmp.create')
                    ->name('store');
                Route::get('/{ppmp}', [OfficePpmpController::class, 'show'])
                    ->middleware('can:office.ppmp.view,ppmp')
                    ->name('show');
                Route::get('/{ppmp}/edit', [OfficePpmpController::class, 'edit'])
                    ->middleware('can:office.ppmp.update,ppmp')
                    ->name('edit');
                Route::put('/{ppmp}', [OfficePpmpController::class, 'update'])
                    ->middleware('can:office.ppmp.update,ppmp')
                    ->name('update');
                Route::post('/{ppmp}/submit', [OfficePpmpController::class, 'submit'])
                    ->middleware('can:office.ppmp.submit,ppmp')
                    ->name('submit');

                Route::get('/{ppmp}/items', [OfficePpmpItemController::class, 'index'])
                    ->middleware('can:office.ppmp.view,ppmp')
                    ->name('items.index');
                Route::post('/{ppmp}/items', [OfficePpmpItemController::class, 'store'])
                    ->middleware('can:office.ppmp.update,ppmp')
                    ->name('items.store');
                Route::put('/{ppmp}/items/{ppmpItem}', [OfficePpmpItemController::class, 'update'])
                    ->middleware('can:office.ppmp.update,ppmp')
                    ->name('items.update');
                Route::delete('/{ppmp}/items/{ppmpItem}', [OfficePpmpItemController::class, 'destroy'])
                    ->middleware('can:office.ppmp.update,ppmp')
                    ->name('items.destroy');
            });

            Route::prefix('supply-requests')->name('supply-requests.')->group(function () {
                Route::get('/', [OfficeSupplyRequestController::class, 'index'])
                    ->middleware('can:office.supply-request.view-any')
                    ->name('index');
                Route::get('/ppmp/{ppmp}/items', [OfficeSupplyRequestController::class, 'ppmpItemsForPpmp'])
                    ->middleware('can:office.supply-request.create')
                    ->name('ppmp-items');
                Route::get('/create', [OfficeSupplyRequestController::class, 'create'])
                    ->middleware('can:office.supply-request.create')
                    ->name('create');
                Route::post('/', [OfficeSupplyRequestController::class, 'store'])
                    ->middleware('can:office.supply-request.create')
                    ->name('store');
                Route::get('/{supply_request}', [OfficeSupplyRequestController::class, 'show'])
                    ->middleware('can:office.supply-request.view,supply_request')
                    ->name('show');
                Route::get('/{supply_request}/edit', [OfficeSupplyRequestController::class, 'edit'])
                    ->middleware('can:office.supply-request.update,supply_request')
                    ->name('edit');
                Route::put('/{supply_request}', [OfficeSupplyRequestController::class, 'update'])
                    ->middleware('can:office.supply-request.update,supply_request')
                    ->name('update');
                Route::post('/{supply_request}/submit', [OfficeSupplyRequestController::class, 'submit'])
                    ->middleware('can:office.supply-request.submit,supply_request')
                    ->name('submit');

                Route::get('/{supply_request}/items', [OfficeSupplyRequestItemController::class, 'index'])
                    ->middleware('can:office.supply-request.view,supply_request')
                    ->name('items.index');
                Route::post('/{supply_request}/items', [OfficeSupplyRequestItemController::class, 'store'])
                    ->middleware('can:office.supply-request.update,supply_request')
                    ->name('items.store');
                Route::put('/{supply_request}/items/{supplyRequestItem}', [OfficeSupplyRequestItemController::class, 'update'])
                    ->middleware('can:office.supply-request.update,supply_request')
                    ->name('items.update');
                Route::delete('/{supply_request}/items/{supplyRequestItem}', [OfficeSupplyRequestItemController::class, 'destroy'])
                    ->middleware('can:office.supply-request.update,supply_request')
                    ->name('items.destroy');
            });

            Route::prefix('distribution-records')->name('distribution-records.')->group(function () {
                Route::get('/{distribution_record}', [DistributionRecordController::class, 'show'])
                    ->middleware('can:distribution.view,distribution_record')
                    ->name('show');
                Route::post('/{distribution_record}/receive', [DistributionRecordController::class, 'receive'])
                    ->middleware('can:distribution.receive,distribution_record')
                    ->name('receive');
            });

            Route::prefix('claims')->name('claims.')->group(function () {
                Route::get('/', [ClaimController::class, 'index'])
                    ->middleware('can:claim.view-any')
                    ->name('index');
                Route::get('/create', [ClaimController::class, 'create'])
                    ->middleware('can:claim.create')
                    ->name('create');
                Route::post('/', [ClaimController::class, 'store'])
                    ->middleware('can:claim.create')
                    ->name('store');
                Route::get('/{claim}', [ClaimController::class, 'show'])
                    ->middleware('can:claim.view,claim')
                    ->name('show');
            });
        });

    Route::prefix('procurement')
        ->name('procurement.')
        ->middleware(['verified', 'domain:procurement'])
        ->group(function () {
            Route::get('/', [DomainDashboardController::class, 'procurement'])
                ->name('dashboard');

            Route::get('/tracking', [DomainDashboardController::class, 'procurement'])
                ->name('tracking');

            Route::get('/documents', [ProcurementDocumentController::class, 'index'])
                ->middleware('can:procurement.documents.view-any')
                ->name('documents.index');

            Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
                Route::get('/', [ProcurementPurchaseRequestController::class, 'index'])
                    ->middleware('can:procurement.purchase-request.view-any')
                    ->name('index');
                Route::get('/create', [ProcurementPurchaseRequestController::class, 'create'])
                    ->middleware('can:procurement.purchase-request.create')
                    ->name('create');
                Route::post('/', [ProcurementPurchaseRequestController::class, 'store'])
                    ->middleware('can:procurement.purchase-request.create')
                    ->name('store');
                Route::get('/{purchase_request}', [ProcurementPurchaseRequestController::class, 'show'])
                    ->middleware('can:procurement.purchase-request.view,purchase_request')
                    ->name('show');
                Route::get('/{purchase_request}/edit', [ProcurementPurchaseRequestController::class, 'edit'])
                    ->middleware('can:procurement.purchase-request.update,purchase_request')
                    ->name('edit');
                Route::put('/{purchase_request}', [ProcurementPurchaseRequestController::class, 'update'])
                    ->middleware('can:procurement.purchase-request.update,purchase_request')
                    ->name('update');
                Route::post('/{purchase_request}/process', [ProcurementPurchaseRequestController::class, 'process'])
                    ->middleware('can:procurement.purchase-request.process,purchase_request')
                    ->name('process');

                Route::get('/{purchase_request}/items', [ProcurementPurchaseRequestItemController::class, 'index'])
                    ->middleware('can:procurement.purchase-request.view,purchase_request')
                    ->name('items.index');
                Route::post('/{purchase_request}/items', [ProcurementPurchaseRequestItemController::class, 'store'])
                    ->middleware('can:procurement.purchase-request.update,purchase_request')
                    ->name('items.store');
                Route::put('/{purchase_request}/items/{purchaseRequestItem}', [ProcurementPurchaseRequestItemController::class, 'update'])
                    ->middleware('can:procurement.purchase-request.update,purchase_request')
                    ->name('items.update');
            });

            Route::prefix('suppliers')->name('suppliers.')->group(function () {
                Route::get('/', [ProcurementSupplierController::class, 'index'])
                    ->middleware('can:procurement.supplier.view-any')
                    ->name('index');
                Route::get('/create', [ProcurementSupplierController::class, 'create'])
                    ->middleware('can:procurement.supplier.create')
                    ->name('create');
                Route::post('/', [ProcurementSupplierController::class, 'store'])
                    ->middleware('can:procurement.supplier.create')
                    ->name('store');
                Route::get('/{supplier}', [ProcurementSupplierController::class, 'show'])
                    ->middleware('can:procurement.supplier.view,supplier')
                    ->name('show');
                Route::get('/{supplier}/edit', [ProcurementSupplierController::class, 'edit'])
                    ->middleware('can:procurement.supplier.update,supplier')
                    ->name('edit');
                Route::put('/{supplier}', [ProcurementSupplierController::class, 'update'])
                    ->middleware('can:procurement.supplier.update,supplier')
                    ->name('update');
            });

            Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
                Route::get('/', [ProcurementPurchaseOrderController::class, 'index'])
                    ->middleware('can:procurement.purchase-order.view-any')
                    ->name('index');
                Route::get('/records', [ProcurementPurchaseOrderController::class, 'records'])
                    ->middleware('can:procurement.purchase-order.view-any')
                    ->name('records');
                Route::get('/create', [ProcurementPurchaseOrderController::class, 'create'])
                    ->middleware('can:procurement.purchase-order.create')
                    ->name('create');
                Route::post('/', [ProcurementPurchaseOrderController::class, 'store'])
                    ->middleware('can:procurement.purchase-order.create')
                    ->name('store');
                Route::get('/{purchase_order}', [ProcurementPurchaseOrderController::class, 'show'])
                    ->middleware('can:procurement.purchase-order.view,purchase_order')
                    ->name('show');
                Route::get('/{purchase_order}/edit', [ProcurementPurchaseOrderController::class, 'edit'])
                    ->middleware('can:procurement.purchase-order.update,purchase_order')
                    ->name('edit');
                Route::put('/{purchase_order}', [ProcurementPurchaseOrderController::class, 'update'])
                    ->middleware('can:procurement.purchase-order.update,purchase_order')
                    ->name('update');
                Route::post('/{purchase_order}/place', [ProcurementPurchaseOrderController::class, 'place'])
                    ->middleware('can:procurement.purchase-order.place,purchase_order')
                    ->name('place');

                Route::get('/{purchase_order}/items', [ProcurementPurchaseOrderItemController::class, 'index'])
                    ->middleware('can:procurement.purchase-order.view,purchase_order')
                    ->name('items.index');
                Route::post('/{purchase_order}/items', [ProcurementPurchaseOrderItemController::class, 'store'])
                    ->middleware('can:procurement.purchase-order.update,purchase_order')
                    ->name('items.store');
                Route::put('/{purchase_order}/items/{purchaseOrderItem}', [ProcurementPurchaseOrderItemController::class, 'update'])
                    ->middleware('can:procurement.purchase-order.update,purchase_order')
                    ->name('items.update');
            });

            Route::prefix('alerts')->name('alerts.')->group(function () {
                Route::get('/', [ExecutiveAlertController::class, 'index'])
                    ->middleware('can:alert.view-any')
                    ->name('index');
                Route::get('/{alert}', [ExecutiveAlertController::class, 'show'])
                    ->middleware('can:alert.view,alert')
                    ->name('show');
            });
        });

    Route::prefix('inventory')
        ->name('inventory.')
        ->middleware(['verified', 'domain:inventory'])
        ->group(function () {
            Route::get('/', [DomainDashboardController::class, 'inventory'])
                ->name('dashboard');

            Route::prefix('inventory')->name('inventory.')->group(function () {
                Route::get('/', [InventoryController::class, 'index'])
                    ->middleware('can:inventory.view-any')
                    ->name('index');
                Route::get('/{inventory}', [InventoryController::class, 'show'])
                    ->middleware('can:inventory.view,inventory')
                    ->name('show');
                Route::put('/{inventory}', [InventoryController::class, 'update'])
                    ->middleware('can:inventory.manage,inventory')
                    ->name('update');
                Route::post('/sync-from-delivery', [InventoryController::class, 'syncFromDelivery'])
                    ->middleware('can:inventory.manage')
                    ->name('sync-from-delivery');
            });

            Route::prefix('inventory-movements')->name('inventory-movements.')->group(function () {
                Route::get('/', [InventoryMovementController::class, 'index'])
                    ->middleware('can:inventory-movement.view-any')
                    ->name('index');
                Route::get('/{movement}', [InventoryMovementController::class, 'show'])
                    ->middleware('can:inventory-movement.view,movement')
                    ->name('show');
            });

            Route::prefix('item-categories')->name('item-categories.')->group(function () {
                Route::get('/', [ItemCategoryController::class, 'index'])
                    ->middleware('can:inventory.catalog.view-any')
                    ->name('index');
                Route::get('/create', [ItemCategoryController::class, 'create'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('create');
                Route::post('/', [ItemCategoryController::class, 'store'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('store');
                Route::get('/{item_category}', [ItemCategoryController::class, 'show'])
                    ->middleware('can:inventory.catalog.view-any')
                    ->name('show');
                Route::get('/{item_category}/edit', [ItemCategoryController::class, 'edit'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('edit');
                Route::put('/{item_category}', [ItemCategoryController::class, 'update'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('update');
                Route::patch('/{item_category}/toggle-status', [ItemCategoryController::class, 'toggleStatus'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('toggle-status');
            });

            Route::prefix('items')->name('items.')->group(function () {
                Route::get('/', [ItemCatalogController::class, 'index'])
                    ->middleware('can:inventory.catalog.view-any')
                    ->name('index');
                Route::get('/create', [ItemCatalogController::class, 'create'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('create');
                Route::post('/', [ItemCatalogController::class, 'store'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('store');
                Route::get('/{item}', [ItemCatalogController::class, 'show'])
                    ->middleware('can:inventory.catalog.view-any')
                    ->name('show');
                Route::get('/{item}/edit', [ItemCatalogController::class, 'edit'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('edit');
                Route::put('/{item}', [ItemCatalogController::class, 'update'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('update');
                Route::patch('/{item}/toggle-status', [ItemCatalogController::class, 'toggleStatus'])
                    ->middleware('can:inventory.catalog.manage')
                    ->name('toggle-status');
            });

            Route::prefix('alerts')->name('alerts.')->group(function () {
                Route::get('/', [ExecutiveAlertController::class, 'index'])
                    ->middleware('can:alert.view-any')
                    ->name('index');
                Route::get('/{alert}', [ExecutiveAlertController::class, 'show'])
                    ->middleware('can:alert.view,alert')
                    ->name('show');
            });

            Route::prefix('distribution-records')->name('distribution-records.')->group(function () {
                Route::get('/', [DistributionRecordController::class, 'index'])
                    ->middleware('can:distribution.view-any')
                    ->name('index');
                Route::get('/create', [DistributionRecordController::class, 'create'])
                    ->middleware('can:distribution.manage')
                    ->name('create');
                Route::post('/', [DistributionRecordController::class, 'store'])
                    ->middleware('can:distribution.manage')
                    ->name('store');
                Route::get('/{distribution_record}', [DistributionRecordController::class, 'show'])
                    ->middleware('can:distribution.view,distribution_record')
                    ->name('show');
                Route::get('/{distribution_record}/edit', [DistributionRecordController::class, 'edit'])
                    ->middleware('can:distribution.manage,distribution_record')
                    ->name('edit');
                Route::get('/{distribution_record}/items/manage', [DistributionRecordController::class, 'manageItems'])
                    ->middleware('can:distribution.manage,distribution_record')
                    ->name('items.manage');
                Route::put('/{distribution_record}', [DistributionRecordController::class, 'update'])
                    ->middleware('can:distribution.manage,distribution_record')
                    ->name('update');
                Route::post('/{distribution_record}/receive', [DistributionRecordController::class, 'receive'])
                    ->middleware('can:distribution.receive,distribution_record')
                    ->name('receive');

                Route::post('/{distribution_record}/items', [DistributionItemController::class, 'store'])
                    ->middleware('can:distribution.manage,distribution_record')
                    ->name('items.store');
                Route::put('/{distribution_record}/items/{distribution_item}', [DistributionItemController::class, 'update'])
                    ->middleware('can:distribution.manage,distribution_record')
                    ->name('items.update');
            });

            Route::prefix('claims')->name('claims.')->group(function () {
                Route::get('/', [ClaimController::class, 'index'])
                    ->middleware('can:claim.view-any')
                    ->name('index');
                Route::get('/create', [ClaimController::class, 'create'])
                    ->middleware('can:claim.create')
                    ->name('create');
                Route::post('/', [ClaimController::class, 'store'])
                    ->middleware('can:claim.create')
                    ->name('store');
                Route::get('/{claim}', [ClaimController::class, 'show'])
                    ->middleware('can:claim.view,claim')
                    ->name('show');
                Route::put('/{claim}/status', [ClaimController::class, 'updateStatus'])
                    ->middleware('can:claim.update,claim')
                    ->name('update-status');
            });

            Route::prefix('office-supply-requests')->name('office-supply-requests.')->group(function () {
                Route::get('/', [RequestWorkflowController::class, 'officeSupplyRequests'])
                    ->middleware('can:inventory.view-any')
                    ->name('index');
            });

            Route::prefix('procurement-requests')->name('procurement-requests.')->group(function () {
                Route::get('/', [RequestWorkflowController::class, 'procurementRequests'])
                    ->middleware('can:inventory.view-any')
                    ->name('index');
            });

            Route::prefix('deliveries')->name('deliveries.')->group(function () {
                Route::get('/', [DeliveryController::class, 'index'])
                    ->middleware('can:delivery.view-any')
                    ->name('index');
                Route::get('/create', [DeliveryController::class, 'create'])
                    ->middleware('can:delivery.create')
                    ->name('create');
                Route::post('/', [DeliveryController::class, 'store'])
                    ->middleware('can:delivery.create')
                    ->name('store');
                Route::get('/{delivery}', [DeliveryController::class, 'show'])
                    ->middleware('can:delivery.view,delivery')
                    ->name('show');
                Route::get('/{delivery}/edit', [DeliveryController::class, 'edit'])
                    ->middleware('can:delivery.update,delivery')
                    ->name('edit');
                Route::put('/{delivery}', [DeliveryController::class, 'update'])
                    ->middleware('can:delivery.update,delivery')
                    ->name('update');

                Route::post('/{delivery}/items', [DeliveryItemController::class, 'store'])
                    ->middleware('can:delivery.update,delivery')
                    ->name('items.store');
                Route::put('/{delivery}/items/{item}', [DeliveryItemController::class, 'update'])
                    ->middleware('can:delivery.update,delivery')
                    ->name('items.update');
            });

            Route::prefix('deliveries')->name('delivery-verifications.')->group(function () {
                Route::get('/{delivery}/verify', [DeliveryVerificationController::class, 'create'])
                    ->middleware('can:delivery-verification.create,delivery')
                    ->name('create');
                Route::post('/{delivery}/verify', [DeliveryVerificationController::class, 'store'])
                    ->middleware('can:delivery-verification.create,delivery')
                    ->name('store');
            });
        });

    Route::prefix('executive')
        ->name('executive.')
        ->middleware(['verified', 'domain:executive'])
        ->group(function () {
            Route::get('/', [ExecutiveMonitoringController::class, 'dashboard'])
                ->middleware('can:executive.monitoring.view')
                ->name('dashboard');

            Route::get('/procurement-analytics', [ExecutiveMonitoringController::class, 'procurementAnalytics'])
                ->middleware('can:executive.monitoring.view')
                ->name('procurement-analytics');

            Route::get('/anomalies-monitoring', [ExecutiveMonitoringController::class, 'anomaliesMonitoring'])
                ->middleware('can:executive.monitoring.view')
                ->name('anomalies-monitoring');

            Route::get('/reports', [ExecutiveMonitoringController::class, 'reports'])
                ->middleware('can:executive.monitoring.view')
                ->name('reports');

            Route::prefix('ppmp-approvals')->name('ppmp-approvals.')->group(function () {
                Route::get('/', [PpmpApprovalController::class, 'index'])
                    ->middleware('can:executive.ppmp-approval.view-any')
                    ->name('index');
                Route::get('/{ppmp}', [PpmpApprovalController::class, 'show'])
                    ->middleware('can:executive.ppmp-approval.view,ppmp')
                    ->name('show');
                Route::put('/{ppmp}/status', [PpmpApprovalController::class, 'updateStatus'])
                    ->middleware('can:executive.ppmp-approval.signoff,ppmp')
                    ->name('update-status');
            });

            Route::get('/transparency-monitoring', [ExecutiveMonitoringController::class, 'transparencyMonitoring'])
                ->middleware('can:executive.monitoring.view')
                ->name('transparency-monitoring');

            Route::get('/monitoring', [ExecutiveMonitoringController::class, 'anomaliesMonitoring'])
                ->middleware('can:executive.monitoring.view')
                ->name('monitoring.summary');

            Route::get('/audit/business', [AuditLogController::class, 'business'])
                ->middleware('can:audit.business.view')
                ->name('audit.business');

            Route::prefix('anomalies')->name('anomalies.')->group(function () {
                Route::get('/', [ExecutiveMonitoringController::class, 'anomaliesMonitoring'])
                    ->middleware('can:anomaly.view-any')
                    ->name('index');
                Route::post('/detect', [AnomalyController::class, 'runDetections'])
                    ->middleware('can:anomaly.view-any')
                    ->name('detect');
                Route::get('/{anomaly}', [AnomalyController::class, 'show'])
                    ->middleware('can:anomaly.view,anomaly')
                    ->name('show');
                Route::put('/{anomaly}/status', [AnomalyController::class, 'updateStatus'])
                    ->middleware('can:anomaly.review,anomaly')
                    ->name('update-status');
            });

            Route::prefix('alerts')->name('alerts.')->group(function () {
                Route::get('/', [ExecutiveMonitoringController::class, 'anomaliesMonitoring'])
                    ->middleware('can:alert.view-any')
                    ->name('index');
                Route::get('/{alert}', [ExecutiveAlertController::class, 'show'])
                    ->middleware('can:alert.view,alert')
                    ->name('show');
                Route::put('/{alert}/status', [ExecutiveAlertController::class, 'updateStatus'])
                    ->middleware('can:alert.review,alert')
                    ->name('update-status');
            });

            Route::prefix('disclosures')->name('disclosures.')->group(function () {
                Route::get('/', [PublicDisclosureController::class, 'index'])
                    ->middleware('can:public-disclosure.view')
                    ->name('index');
                Route::get('/create', [PublicDisclosureController::class, 'create'])
                    ->middleware('can:public-disclosure.manage')
                    ->name('create');
                Route::post('/', [PublicDisclosureController::class, 'store'])
                    ->middleware('can:public-disclosure.manage')
                    ->name('store');
                Route::get('/{public_disclosure}', [PublicDisclosureController::class, 'show'])
                    ->middleware('can:public-disclosure.view')
                    ->name('show');
                Route::put('/{public_disclosure}/publish', [PublicDisclosureController::class, 'publish'])
                    ->middleware('can:public-disclosure.publish,public_disclosure')
                    ->name('publish');
            });
        });
});

Route::prefix('public')
    ->name('public.')
    ->group(function () {
        Route::get('/', [PublicDisclosurePortalController::class, 'index'])
            ->middleware('can:public.disclosures.view')
            ->name('dashboard');
        Route::get('/disclosures', [PublicDisclosurePortalController::class, 'index'])
            ->middleware('can:public.disclosures.view')
            ->name('disclosures.index');
        Route::get('/disclosures/{public_disclosure}', [PublicDisclosurePortalController::class, 'show'])
            ->middleware('can:public.disclosures.view')
            ->name('disclosures.show');
        Route::get('/disclosures/{public_disclosure}/documents/{document}', [DocumentController::class, 'downloadPublicDisclosureDocument'])
            ->middleware('can:public.disclosures.view')
            ->name('disclosures.documents.download');
    });

require __DIR__.'/auth.php';
