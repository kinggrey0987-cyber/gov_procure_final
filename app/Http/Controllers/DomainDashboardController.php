<?php

namespace App\Http\Controllers;

use App\Models\Ppmp;
use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DomainDashboardController extends Controller
{
    public function adminTech(): Response
    {
        return Inertia::render('Domain/Placeholder', [
            'title' => 'Technical Administration',
            'description' => 'ADMIN-only technical administration entry point.',
        ]);
    }

    public function office(Request $request): Response
    {
        $officeId = (int) $request->user()?->office_id;

        return Inertia::render('Office/Tracking', [
            'summary' => [
                'draft_ppmp_count' => Ppmp::query()->forOffice($officeId)->draft()->count(),
                'submitted_ppmp_count' => Ppmp::query()->forOffice($officeId)->submitted()->count(),
                'draft_supply_request_count' => SupplyRequest::query()->forOffice($officeId)->draft()->count(),
                'submitted_supply_request_count' => SupplyRequest::query()->forOffice($officeId)->submitted()->count(),
                'open_purchase_request_count' => PurchaseRequest::query()->forOffice($officeId)->open()->count(),
                'processing_purchase_request_count' => PurchaseRequest::query()->forOffice($officeId)->processing()->count(),
                'placed_purchase_order_count' => PurchaseOrder::query()
                    ->whereHas('purchaseRequest', fn ($query) => $query->where('office_id', $officeId))
                    ->placed()
                    ->count(),
            ],
        ]);
    }

    public function procurement(): Response
    {
        return Inertia::render('Procurement/Tracking', [
            'summary' => [
                'open_purchase_request_count' => PurchaseRequest::query()->open()->count(),
                'processing_purchase_request_count' => PurchaseRequest::query()->processing()->count(),
                'draft_purchase_order_count' => PurchaseOrder::query()->draft()->count(),
                'placed_purchase_order_count' => PurchaseOrder::query()->placed()->count(),
            ],
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
