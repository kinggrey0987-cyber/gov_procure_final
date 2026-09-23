import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import PaginationNav from '@/Components/PaginationNav';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PurchaseRequestRow = {
    purchase_request_id: number;
    pr_number?: string | null;
    status: string;
    created_at?: string;
    office?: { name?: string | null } | null;
    supply_request?: {
        request_code?: string | null;
    } | null;
};

type ShortageCandidate = {
    supply_request_id: number;
    request_code: string;
    office?: { name?: string | null } | null;
    needed_by_date?: string | null;
    requested_total: number;
    shortage_total: number;
};

type LowStockItem = {
    inventory_id: number;
    item_name: string;
    specifications?: Array<{
        item_specification_id: number;
        specification: string;
    }>;
    unit?: string;
    pieces_per_box?: number;
    sub_unit?: string;
    available_stock: number;
    low_stock_threshold: number;
    deficit: number;
    suggested_quantity: number;
    is_already_queued: boolean;
    active_purchase_request_id?: number | null;
};

type Props = {
    purchaseRequests: {
        data: PurchaseRequestRow[];
        links: PaginationLink[];
        current_page?: number;
    };
    shortageCandidates: ShortageCandidate[];
    lowStockItems: LowStockItem[];
    atReorderItems: Array<{
        inventory_id: number;
        item_name: string;
        specifications?: Array<{
            item_specification_id: number;
            specification: string;
        }>;
        unit?: string;
        pieces_per_box?: number;
        sub_unit?: string;
        available_stock: number;
        low_stock_threshold: number;
    }>;
    focusInventoryId?: number | null;
};

export default function ProcurementRequestsIndex({ purchaseRequests, shortageCandidates, lowStockItems, atReorderItems, focusInventoryId = null }: Props) {
    const [initiatingInventoryId, setInitiatingInventoryId] = useState<number | null>(null);
    const [quantityByInventoryId, setQuantityByInventoryId] = useState<Record<number, string>>({});
    const [selectedLowStockItem, setSelectedLowStockItem] = useState<LowStockItem | null>(null);
    const [modalQuantity, setModalQuantity] = useState('');

    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['purchaseRequests', 'shortageCandidates', 'lowStockItems', 'atReorderItems', 'focusInventoryId'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const initiateLowStockProcurement = (item: LowStockItem, quantityOverride?: string) => {
        const rawQuantity = quantityOverride ?? quantityByInventoryId[item.inventory_id] ?? String(item.suggested_quantity);
        const parsedQuantity = Number(rawQuantity);
        const quantityRequested = Number.isFinite(parsedQuantity) && parsedQuantity > 0
            ? parsedQuantity
            : item.suggested_quantity;

        setInitiatingInventoryId(item.inventory_id);
        router.post(route('inventory.procurement-requests.initiate-low-stock', item.inventory_id), {
            quantity_requested: quantityRequested,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setInitiatingInventoryId(null);
                setSelectedLowStockItem((current) => (current?.inventory_id === item.inventory_id ? null : current));
            },
        });
    };

    const openLowStockModal = (item: LowStockItem) => {
        if (item.is_already_queued) {
            return;
        }

        setSelectedLowStockItem(item);
        setModalQuantity(quantityByInventoryId[item.inventory_id] ?? item.suggested_quantity.toFixed(2));
    };

    const closeLowStockModal = () => {
        if (selectedLowStockItem && initiatingInventoryId === selectedLowStockItem.inventory_id) {
            return;
        }

        setSelectedLowStockItem(null);
    };

    const confirmLowStockProcurement = () => {
        if (!selectedLowStockItem) {
            return;
        }

        setQuantityByInventoryId((prev) => ({
            ...prev,
            [selectedLowStockItem.inventory_id]: modalQuantity,
        }));

        initiateLowStockProcurement(selectedLowStockItem, modalQuantity);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Requests</h2>
                    <button
                        type="button"
                        onClick={() => void refreshNow()}
                        disabled={isRefreshing}
                        className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition shadow-2xs"
                    >
                        {isRefreshing ? 'Refreshing…' : 'Refresh Queue'}
                    </button>
                </div>
            }
        >
            <Head title="Procurement Requests" />

            <div className="space-y-6 py-8">

                {focusInventoryId ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm">
                        Focused inventory signal loaded for record #{focusInventoryId}. Use the shortage queue and initiation actions below to proceed.
                    </div>
                ) : null}

                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Shortage-Based Initiation Queue</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            This queue identifies office requests that cannot be fulfilled from current stock. It prepares purchase request initiation inputs and does not perform
                            Procurement Unit approval or adjudication.
                        </p>
                        <p className="mt-2 text-xs text-slate-500">Shortage values are computed against currently available stock in inventory.</p>
                        <div className="mt-4 space-y-3">
                            {shortageCandidates.slice(0, 6).map((candidate) => (
                                <div key={candidate.supply_request_id} className="rounded-lg border border-rose-100 bg-rose-50/60 p-3">
                                    <p className="text-sm font-semibold text-slate-800">{candidate.request_code}</p>
                                    <p className="text-xs text-slate-600">{candidate.office?.name ?? '-'} • Needed by {candidate.needed_by_date ?? '-'}</p>
                                    <p className="mt-1 text-xs font-semibold text-rose-700">Shortage: {candidate.shortage_total.toFixed(2)}</p>
                                </div>
                            ))}
                            {shortageCandidates.length === 0 ? <p className="text-sm text-slate-500">No active shortages requiring purchase request initiation.</p> : null}
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Proactive GSO Demand Forecast Signals</h3>
                        <p className="mt-2 text-xs text-slate-500">
                            The system uses a statistical demand signal to flag supplies before demand outpaces stock. Reorder threshold is the minimum safety stock level, and forecast deficit is the gap between current available stock and that threshold. These signals support proactive GSO purchase request creation only; they are not automated PPMP approval, procurement approval, or PO approval.
                        </p>
                        <div className="mt-4 space-y-3">
                            {lowStockItems.slice(0, 6).map((item) => {
                                const piecesPerBox = item.pieces_per_box ?? 1;
                                const unit = item.unit || 'box';
                                const subUnit = item.sub_unit || 'piece';

                                return (
                                <div key={item.inventory_id} className={`rounded-lg border border-amber-100 bg-amber-50/70 p-3 ${focusInventoryId === item.inventory_id ? 'ring-2 ring-amber-300' : ''}`}>
                                    <div className="flex flex-wrap items-center justify-between gap-1">
                                        <p className="text-sm font-semibold text-slate-800">{item.item_name}</p>
                                        {piecesPerBox > 1 ? (
                                            <span className="text-xs font-semibold text-indigo-700">
                                                [{unit} ({piecesPerBox} {subUnit}s/{unit})]
                                            </span>
                                        ) : null}
                                    </div>
                                    {item.specifications && item.specifications.length > 0 ? (
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            {item.specifications.map((spec) => (
                                                <span
                                                    key={spec.item_specification_id}
                                                    className="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 border border-blue-200"
                                                >
                                                    {spec.specification}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null}
                                    <p className="text-xs text-slate-600 mt-1.5">
                                        Available: <span className="font-semibold text-slate-700">{item.available_stock.toFixed(2)} {unit}s</span> {piecesPerBox > 1 ? `(${(item.available_stock * piecesPerBox).toLocaleString()} ${subUnit}s)` : ''} / Reorder Point: <span className="font-semibold text-slate-700">{item.low_stock_threshold.toFixed(2)} {unit}s</span> {piecesPerBox > 1 ? `(${(item.low_stock_threshold * piecesPerBox).toLocaleString()} ${subUnit}s)` : ''}
                                    </p>
                                    <p className="mt-1 text-xs font-semibold text-amber-700">
                                        Forecast Deficit: {item.deficit.toFixed(2)} {unit}s {piecesPerBox > 1 ? `(${(item.deficit * piecesPerBox).toLocaleString()} ${subUnit}s)` : ''}
                                    </p>
                                    <button
                                        type="button"
                                        disabled={initiatingInventoryId === item.inventory_id || item.is_already_queued}
                                        onClick={() => openLowStockModal(item)}
                                        className="mt-2 inline-flex rounded-md border border-red-300 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-100 disabled:text-slate-500"
                                    >
                                        {item.is_already_queued
                                            ? 'Already Queued for Procurement'
                                            : initiatingInventoryId === item.inventory_id
                                            ? 'Creating Proactive PR...'
                                            : 'Review Proactive PR Signal'}
                                    </button>
                                    {item.is_already_queued && item.active_purchase_request_id ? (
                                        <p className="mt-1 text-[11px] font-semibold text-slate-500">Active PR: PR-{String(item.active_purchase_request_id).padStart(6, '0')}</p>
                                    ) : null}
                                </div>
                                );
                            })}
                            {lowStockItems.length === 0 ? <p className="text-sm text-slate-500">No low-stock items at the moment.</p> : null}
                        </div>

                        <div className="mt-5 rounded-lg border border-blue-100 bg-blue-50/70 p-3">
                            <h4 className="text-xs font-semibold uppercase tracking-wide text-blue-700">At Forecast Reorder Point (Advisory)</h4>
                            <p className="mt-1 text-xs text-blue-800">
                                These supplies are exactly at the statistical reorder threshold with zero forecast deficit, so they are watchlist cases rather than active proactive PR cases.
                            </p>
                            <div className="mt-3 space-y-2">
                                {atReorderItems.slice(0, 5).map((item) => {
                                    const piecesPerBox = item.pieces_per_box ?? 1;
                                    const unit = item.unit || 'box';
                                    const subUnit = item.sub_unit || 'piece';

                                    return (
                                    <p key={item.inventory_id} className="text-xs text-slate-700">
                                        <span className="font-medium">{item.item_name}</span>
                                        {item.specifications && item.specifications.length > 0 ? (
                                            <span className="text-slate-500 text-[11px] ml-1">
                                                ({item.specifications.map((s) => s.specification).join(', ')})
                                            </span>
                                        ) : null}
                                        : Available {item.available_stock.toFixed(2)} {unit}s {piecesPerBox > 1 ? `(${(item.available_stock * piecesPerBox).toLocaleString()} ${subUnit}s)` : ''} / Reorder Point {item.low_stock_threshold.toFixed(2)} {unit}s
                                    </p>
                                    );
                                })}
                                {atReorderItems.length === 0 ? <p className="text-xs text-slate-500">No supplies are exactly at the forecast reorder point.</p> : null}
                            </div>
                        </div>
                    </div>
                </section>

                <Modal show={selectedLowStockItem !== null} maxWidth="lg" onClose={closeLowStockModal}>
                    <div className="space-y-4 p-6">
                        <div>
                            <h4 className="text-base font-semibold text-slate-900">Review Proactive GSO PR Signal</h4>
                            <p className="mt-1 text-sm text-slate-600">Review the statistical demand signal and adjust the proposed quantity before creating a proactive GSO purchase request. The Procurement Unit must still process the PR and create any PO.</p>
                        </div>

                        {selectedLowStockItem ? (() => {
                            const piecesPerBox = selectedLowStockItem.pieces_per_box ?? 1;
                            const unit = selectedLowStockItem.unit || 'box';
                            const subUnit = selectedLowStockItem.sub_unit || 'piece';
                            const numModalQty = Number(modalQuantity || 0);
                            const modalPieces = numModalQty * piecesPerBox;

                            return (
                            <div className="space-y-2 rounded-lg border border-amber-100 bg-amber-50/70 p-3">
                                <div className="flex flex-wrap items-center justify-between gap-1">
                                    <p className="text-sm font-semibold text-slate-800">{selectedLowStockItem.item_name}</p>
                                    {piecesPerBox > 1 ? (
                                        <span className="text-xs font-semibold text-indigo-700">
                                            [{unit} ({piecesPerBox} {subUnit}s/{unit})]
                                        </span>
                                    ) : null}
                                </div>
                                {selectedLowStockItem.specifications && selectedLowStockItem.specifications.length > 0 ? (
                                    <div className="mt-1 flex flex-wrap items-center gap-1">
                                        <span className="text-[11px] font-semibold text-slate-500 mr-1">Specs:</span>
                                        {selectedLowStockItem.specifications.map((spec) => (
                                            <span
                                                key={spec.item_specification_id}
                                                className="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 border border-blue-200"
                                            >
                                                {spec.specification}
                                            </span>
                                        ))}
                                    </div>
                                ) : null}
                                <p className="text-xs text-slate-600">
                                    Available: <span className="font-semibold text-slate-700">{selectedLowStockItem.available_stock.toFixed(2)} {unit}s</span> {piecesPerBox > 1 ? `(${(selectedLowStockItem.available_stock * piecesPerBox).toLocaleString()} ${subUnit}s)` : ''} / Reorder Threshold: <span className="font-semibold text-slate-700">{selectedLowStockItem.low_stock_threshold.toFixed(2)} {unit}s</span>
                                </p>
                                <p className="text-xs font-semibold text-amber-700">
                                    Forecast Deficit: {selectedLowStockItem.deficit.toFixed(2)} {unit}s {piecesPerBox > 1 ? `(${(selectedLowStockItem.deficit * piecesPerBox).toLocaleString()} ${subUnit}s)` : ''}
                                </p>

                                <div>
                                    <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                        Proposed PR Quantity ({unit}s)
                                    </label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={modalQuantity}
                                        disabled={initiatingInventoryId === selectedLowStockItem.inventory_id}
                                        onChange={(event) => setModalQuantity(event.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    />
                                    <div className="mt-1 flex flex-wrap items-center justify-between text-[11px] text-slate-500">
                                        <span>Suggested: {selectedLowStockItem.suggested_quantity.toFixed(2)} {unit}s</span>
                                        {numModalQty > 0 && piecesPerBox > 1 ? (
                                            <span className="font-semibold text-indigo-700">
                                                = {modalPieces.toLocaleString()} total {subUnit}s
                                            </span>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                            );
                        })() : null}

                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={closeLowStockModal}
                                disabled={selectedLowStockItem ? initiatingInventoryId === selectedLowStockItem.inventory_id : false}
                                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmLowStockProcurement}
                                disabled={selectedLowStockItem ? initiatingInventoryId === selectedLowStockItem.inventory_id : true}
                                className="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-100 disabled:text-slate-500"
                            >
                                {selectedLowStockItem && initiatingInventoryId === selectedLowStockItem.inventory_id
                                    ? 'Creating Proactive PR...'
                                    : 'Create Proactive GSO PR'}
                            </button>
                        </div>
                    </div>
                </Modal>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Existing Purchase Request Records</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Linked Office Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Reference</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseRequests.data.map((request) => (
                                <tr key={request.purchase_request_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{request.supply_request?.request_code ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{request.office?.name ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(request.status)}>{displayPurchaseRequestStatus(request.status)}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right text-xs font-semibold text-slate-700">{request.pr_number || `PR-${String(request.purchase_request_id).padStart(6, '0')}`}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('inventory.procurement-requests.show', {
                                                purchase_request: request.purchase_request_id,
                                                page: purchaseRequests.current_page || undefined,
                                            })}
                                            className="text-sm font-semibold text-red-700 hover:text-red-900"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {purchaseRequests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No procurement request records available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>

                    {purchaseRequests.links && purchaseRequests.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
                            <span className="text-xs font-medium text-slate-500">
                                Showing {purchaseRequests.data.length} records
                            </span>
                            <PaginationNav links={purchaseRequests.links} />
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function displayPurchaseRequestStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Queued for Procurement',
        PROCESSING: 'In Procurement Processing',
        COMPLETED: 'Completed',
        PENDING: 'Pending Review',
    };

    return map[status] ?? humanizeEnum(status);
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
        RETURNED_FOR_REVISION: `${base} bg-violet-100 text-violet-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}





