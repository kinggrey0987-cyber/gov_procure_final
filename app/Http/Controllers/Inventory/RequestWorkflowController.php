<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Inventory;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use Inertia\Inertia;
use Inertia\Response;

class RequestWorkflowController
{
    public function officeSupplyRequests(): Response
    {
        abort_unless(auth()->user()->can('inventory.view-any'), 403);

        $requests = SupplyRequest::query()
            ->with(['office', 'requester', 'items.item.inventory', 'purchaseRequests'])
            ->withCount('distributionRecords')
            ->latest('supply_request_id')
            ->paginate(15);

        $requests->setCollection(
            $requests->getCollection()->map(function (SupplyRequest $request): array {
                $requestedTotal = 0.0;
                $shortageTotal = 0.0;

                foreach ($request->items as $item) {
                    $requested = (float) $item->quantity_requested;
                    $available = (float) ($item->item?->inventory?->available_stock ?? 0);

                    $requestedTotal += $requested;
                    $shortageTotal += max($requested - $available, 0);
                }

                return [
                    'supply_request_id' => (int) $request->supply_request_id,
                    'request_code' => (string) $request->request_code,
                    'status' => (string) $request->status,
                    'priority_level' => (string) $request->priority_level,
                    'needed_by_date' => optional($request->needed_by_date)?->toDateString(),
                    'office' => $request->office ? [
                        'name' => (string) $request->office->name,
                    ] : null,
                    'requester' => $request->requester ? [
                        'name' => (string) $request->requester->name,
                    ] : null,
                    'item_count' => $request->items->count(),
                    'requested_total' => round($requestedTotal, 2),
                    'shortage_total' => round($shortageTotal, 2),
                    'stock_assessment' => $shortageTotal > 0 ? 'SHORTAGE_REQUIRES_PROCUREMENT' : 'STOCK_READY',
                    'has_purchase_request' => $request->purchaseRequests->isNotEmpty(),
                    'distribution_records_count' => (int) $request->distribution_records_count,
                ];
            })
        );

        return Inertia::render('Inventory/OfficeSupplyRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function procurementRequests(): Response
    {
        abort_unless(auth()->user()->can('inventory.view-any'), 403);

        $purchaseRequests = PurchaseRequest::query()
            ->with(['office', 'requester', 'supplyRequest'])
            ->latest('purchase_request_id')
            ->paginate(12);

        $shortageCandidates = SupplyRequest::query()
            ->submitted()
            ->whereDoesntHave('purchaseRequests')
            ->with(['office', 'items.item.inventory'])
            ->latest('supply_request_id')
            ->limit(20)
            ->get()
            ->map(function (SupplyRequest $request): array {
                $requestedTotal = 0.0;
                $shortageTotal = 0.0;

                foreach ($request->items as $item) {
                    $requested = (float) $item->quantity_requested;
                    $available = (float) ($item->item?->inventory?->available_stock ?? 0);

                    $requestedTotal += $requested;
                    $shortageTotal += max($requested - $available, 0);
                }

                return [
                    'supply_request_id' => (int) $request->supply_request_id,
                    'request_code' => (string) $request->request_code,
                    'office' => $request->office ? [
                        'name' => (string) $request->office->name,
                    ] : null,
                    'needed_by_date' => optional($request->needed_by_date)?->toDateString(),
                    'requested_total' => round($requestedTotal, 2),
                    'shortage_total' => round($shortageTotal, 2),
                ];
            })
            ->filter(fn (array $request): bool => $request['shortage_total'] > 0)
            ->values();

        $lowStockItems = Inventory::query()
            ->with('item')
            ->lowStock()
            ->orderByRaw('(low_stock_threshold - available_stock) DESC')
            ->limit(12)
            ->get()
            ->map(fn (Inventory $inventory): array => [
                'inventory_id' => (int) $inventory->inventory_id,
                'item_name' => (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                'available_stock' => (float) $inventory->available_stock,
                'low_stock_threshold' => (float) $inventory->low_stock_threshold,
                'deficit' => round(max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0), 2),
            ])
            ->values();

        return Inertia::render('Inventory/ProcurementRequests/Index', [
            'purchaseRequests' => $purchaseRequests,
            'shortageCandidates' => $shortageCandidates,
            'lowStockItems' => $lowStockItems,
        ]);
    }
}