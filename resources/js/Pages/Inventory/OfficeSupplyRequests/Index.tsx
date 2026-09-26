import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type RequestRow = {
    supply_request_id: number;
    request_code: string;
    status: string;
    priority_level: string;
    needed_by_date?: string | null;
    office?: { id?: number; name?: string | null } | null;
    requester?: { name?: string | null } | null;
    item_count: number;
    requested_total: number;
    shortage_total: number;
    requested_items: Array<{
        item_name: string;
        quantity_requested: number;
        unit: string;
    }>;
    stock_assessment: 'STOCK_READY' | 'SHORTAGE_REQUIRES_PROCUREMENT';
    has_purchase_request: boolean;
    has_distribution_candidate: boolean;
    distribution_records_count: number;
    workflow_bucket: 'READY_FOR_DISTRIBUTION' | 'QUEUED_FOR_PROCUREMENT' | 'UNDER_VALIDATION' | 'STOCK_READY_AWAITING_RELEASE_CONTEXT';
};

type Props = {
    requests: {
        data: RequestRow[];
        links: PaginationLink[];
    };
    offices: Array<{ id: number; name: string }>;
    filters: {
        q?: string;
        office_id?: number | null;
        stock_assessment?: string;
        workflow_bucket?: string;
        page?: number | null;
    };
    officeShortageGroups?: Array<{
        id: number;
        name: string;
        count: number;
        requests?: RequestRow[];
    }>;
};

type PageProps = {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

export default function OfficeSupplyRequestsIndex({ requests, offices, filters, officeShortageGroups: initialShortageGroups }: Props) {
    const { flash } = usePage().props as unknown as PageProps;
    const [q, setQ] = useState(filters.q ?? '');
    const [officeId, setOfficeId] = useState(String(filters.office_id ?? ''));
    const [stockAssessment, setStockAssessment] = useState(filters.stock_assessment ?? '');
    const [workflowBucket, setWorkflowBucket] = useState(filters.workflow_bucket ?? '');
    const debouncedQ = useDebouncedValue(q, 450);
    const [queueingRequestId, setQueueingRequestId] = useState<number | null>(null);
    const [processingRequestId, setProcessingRequestId] = useState<number | null>(null);
    const [selectedRequestIds, setSelectedRequestIds] = useState<Set<number>>(new Set());
    const [isConsolidating, setIsConsolidating] = useState(false);
    const [showPreviewModal, setShowPreviewModal] = useState(false);
    const [consolidationRemarks, setConsolidationRemarks] = useState('');
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setQ(filters.q ?? '');
        setOfficeId(String(filters.office_id ?? ''));
        setStockAssessment(filters.stock_assessment ?? '');
        setWorkflowBucket(filters.workflow_bucket ?? '');
    }, [filters.office_id, filters.q, filters.stock_assessment, filters.workflow_bucket]);

    const { isRefreshing, lastRefreshedAt, refreshNow } = useSmartRefresh({
        only: ['requests', 'filters'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const activeFilters = useMemo(
        () => ({
            q: debouncedQ,
            office_id: officeId,
            stock_assessment: stockAssessment,
            workflow_bucket: workflowBucket,
        }),
        [debouncedQ, officeId, stockAssessment, workflowBucket],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(
            route('inventory.office-supply-requests.index'),
            activeFilters,
            { preserveState: true, replace: true },
        );
    }, [activeFilters]);

    const queueProcurement = (requestId: number) => {
        setQueueingRequestId(requestId);
        router.post(route('inventory.office-supply-requests.queue', requestId), {}, {
            preserveScroll: true,
            onFinish: () => setQueueingRequestId(null),
        });
    };

    const processDistribution = (requestId: number) => {
        setProcessingRequestId(requestId);
        router.post(route('inventory.office-supply-requests.process-distribution', requestId), {}, {
            preserveScroll: true,
            onFinish: () => setProcessingRequestId(null),
        });
    };

    const [selectedConsolidationOfficeId, setSelectedConsolidationOfficeId] = useState<number | null>(null);

    // Offices with pending shortages eligible for consolidation (global from server or derived from rows)
    // Only includes offices with at least 2 pending shortage requests
    const officeShortageGroups = useMemo(() => {
        if (initialShortageGroups && initialShortageGroups.length > 0) {
            return initialShortageGroups.filter((g) => g.count >= 2);
        }
        const map = new Map<number, { id: number; name: string; count: number; requests: RequestRow[] }>();
        requests.data.forEach((r) => {
            if (r.stock_assessment === 'SHORTAGE_REQUIRES_PROCUREMENT' && r.status === 'SUBMITTED' && !r.has_purchase_request && r.office?.id) {
                const existing = map.get(r.office.id) ?? { id: r.office.id, name: r.office.name ?? 'Office', count: 0, requests: [] };
                existing.count++;
                existing.requests.push(r);
                map.set(r.office.id, existing);
            }
        });
        return Array.from(map.values())
            .filter((g) => g.count >= 2)
            .sort((a, b) => b.count - a.count);
    }, [initialShortageGroups, requests.data]);

    // Active office for consolidation
    const activeConsolidationOffice = useMemo(() => {
        if (officeShortageGroups.length === 0) return null;
        if (selectedConsolidationOfficeId) {
            const found = officeShortageGroups.find((g) => g.id === selectedConsolidationOfficeId);
            if (found) return found;
        }
        return officeShortageGroups[0];
    }, [officeShortageGroups, selectedConsolidationOfficeId]);

    const activeOfficeId = activeConsolidationOffice?.id ?? null;
    const targetOfficeName = activeConsolidationOffice?.name ?? null;

    // Eligible requests for the active office
    const consolidatableRequests = useMemo(() => {
        if (!activeConsolidationOffice) return [];
        if (activeConsolidationOffice.requests && activeConsolidationOffice.requests.length > 0) {
            return activeConsolidationOffice.requests;
        }
        return requests.data.filter((r) => {
            return (
                r.stock_assessment === 'SHORTAGE_REQUIRES_PROCUREMENT' &&
                r.status === 'SUBMITTED' &&
                !r.has_purchase_request &&
                r.office?.id === activeConsolidationOffice.id
            );
        });
    }, [activeConsolidationOffice, requests.data]);

    // Automatically pre-select all eligible requests for the active office
    useEffect(() => {
        if (consolidatableRequests.length >= 2) {
            setSelectedRequestIds(new Set(consolidatableRequests.map((r) => r.supply_request_id)));
        } else {
            setSelectedRequestIds(new Set());
        }
    }, [activeConsolidationOffice?.id, consolidatableRequests.length]);

    const selectedRequests = useMemo(() => {
        return consolidatableRequests.filter((r) => selectedRequestIds.has(r.supply_request_id));
    }, [consolidatableRequests, selectedRequestIds]);

    const canConsolidate = activeOfficeId !== null && selectedRequestIds.size >= 2;

    const toggleSelect = (id: number) => {
        setSelectedRequestIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const toggleSelectAll = () => {
        if (selectedRequestIds.size >= consolidatableRequests.length) {
            setSelectedRequestIds(new Set());
        } else {
            setSelectedRequestIds(new Set(consolidatableRequests.map((r) => r.supply_request_id)));
        }
    };

    // Aggregated item lines for preview modal
    const aggregatedItems = useMemo(() => {
        const itemMap = new Map<string, { item_name: string; unit: string; total_quantity: number }>();
        selectedRequests.forEach((req) => {
            (req.requested_items || []).forEach((item) => {
                const existing = itemMap.get(item.item_name) ?? {
                    item_name: item.item_name,
                    unit: item.unit,
                    total_quantity: 0,
                };
                existing.total_quantity += Number(item.quantity_requested || 0);
                itemMap.set(item.item_name, existing);
            });
        });
        return Array.from(itemMap.values());
    }, [selectedRequests]);

    const submitConsolidatedPr = () => {
        if (!canConsolidate || !activeOfficeId) return;
        setIsConsolidating(true);
        router.post(
            route('inventory.office-supply-requests.consolidated-queue'),
            {
                office_id: activeOfficeId,
                supply_request_ids: Array.from(selectedRequestIds),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsConsolidating(false);
                    setShowPreviewModal(false);
                    setSelectedRequestIds(new Set());
                },
            },
        );
    };

    return (
        <AuthenticatedLayout
            header="Office Supply Requests"
            headerActions={
                <Link
                    href={route('inventory.distribution-records.create')}
                    className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-2xs transition"
                >
                    Open Supply Distribution &rarr;
                </Link>
            }
        >
            <Head title="Office Supply Requests" />

            <div className="space-y-3">
                {flash?.success ? (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                ) : null}
                {flash?.error ? (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700">
                        {flash.error}
                    </div>
                ) : null}

                {/* Compact Single-Row Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Search request code, purpose, or office..."
                            className="h-8 flex-1 min-w-[180px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        />
                        <select
                            value={officeId}
                            onChange={(event) => setOfficeId(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All offices</option>
                            {offices.map((office) => (
                                <option key={office.id} value={office.id}>
                                    {office.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={workflowBucket}
                            onChange={(event) => setWorkflowBucket(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All workflow buckets</option>
                            <option value="READY_FOR_DISTRIBUTION">Ready for Distribution</option>
                            <option value="STOCK_READY_AWAITING_RELEASE_CONTEXT">Stock Ready Awaiting Release Context</option>
                            <option value="QUEUED_FOR_PROCUREMENT">Queued for Procurement</option>
                            <option value="UNDER_VALIDATION">Submitted or Under Validation</option>
                        </select>
                        <select
                            value={stockAssessment}
                            onChange={(event) => setStockAssessment(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All stock assessments</option>
                            <option value="STOCK_READY">Stock Ready</option>
                            <option value="SHORTAGE_REQUIRES_PROCUREMENT">Needs Procurement</option>
                        </select>
                        <button
                            type="button"
                            onClick={() => void refreshNow()}
                            disabled={isRefreshing}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition shadow-2xs"
                        >
                            {isRefreshing ? 'Refreshing…' : 'Refresh'}
                        </button>
                        <Link
                            href={route('inventory.office-supply-requests.index')}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                        >
                            Reset
                        </Link>
                    </div>
                </div>

                {officeShortageGroups.length > 0 && activeConsolidationOffice && (
                    <div className="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-b from-white via-slate-50/40 to-white p-5 sm:p-6 shadow-sm">
                        {/* Top decorative gradient bar */}
                        <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-red-600 via-amber-500 to-red-700" />

                        {/* Hub Header */}
                        <div className="flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3.5">
                                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-red-200/70 bg-red-50 text-red-700 shadow-2xs">
                                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-base font-bold tracking-tight text-slate-900">
                                            Consolidated PR Creation Hub
                                        </h3>
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200/80 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                            <span className="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />
                                            {officeShortageGroups.reduce((acc, g) => acc + g.count, 0)} Shortages Ready for Consolidation
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Batch multiple shortage requests from the same department into one official Purchase Request for BAC procurement.
                                    </p>
                                </div>
                            </div>

                            {/* Multi-Office Selector (Only displayed if 2 or more offices have multiple shortages) */}
                            {officeShortageGroups.length > 1 && (
                                <div className="flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-200/90 bg-slate-100/90 p-1.5 shadow-2xs">
                                    <span className="px-2 text-[11px] font-bold uppercase tracking-wider text-slate-500">Department:</span>
                                    {officeShortageGroups.map((g) => {
                                        const isSelected = g.id === activeOfficeId;
                                        return (
                                            <button
                                                key={g.id}
                                                type="button"
                                                onClick={() => setSelectedConsolidationOfficeId(g.id)}
                                                className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                                    isSelected
                                                        ? 'border border-slate-300/80 bg-white font-bold text-slate-900 shadow-2xs'
                                                        : 'text-slate-600 hover:bg-slate-200/70 hover:text-slate-900'
                                                }`}
                                            >
                                                <span>{g.name}</span>
                                                <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                                                    isSelected ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-600'
                                                }`}>
                                                    {g.count}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Office Context & Action Bar */}
                        <div className="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200/80 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-base shadow-2xs">
                                    🏢
                                </div>
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-xs font-medium text-slate-500">Target Office:</span>
                                        <span className="text-sm font-bold text-slate-900">{targetOfficeName}</span>
                                        <span className="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                            {consolidatableRequests.length} pending requests
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {selectedRequestIds.size === 0
                                            ? 'Select 2 or more requests below to merge into a single PR.'
                                            : `${selectedRequestIds.size} of ${consolidatableRequests.length} requests selected for consolidation.`}
                                    </p>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-wrap items-center gap-2 sm:self-center">
                                <button
                                    type="button"
                                    onClick={toggleSelectAll}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                >
                                    <svg className="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    {selectedRequestIds.size >= consolidatableRequests.length ? 'Deselect All' : `Select All (${consolidatableRequests.length})`}
                                </button>

                                <button
                                    type="button"
                                    disabled={!canConsolidate || isConsolidating}
                                    onClick={() => setShowPreviewModal(true)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-red-700 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>{isConsolidating ? 'Processing...' : `Preview & Forward to BAC (${selectedRequestIds.size})`}</span>
                                </button>
                            </div>
                        </div>

                        {/* Interactive Request Cards */}
                        <div className="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                            {consolidatableRequests.map((request) => {
                                const isSelected = selectedRequestIds.has(request.supply_request_id);
                                return (
                                    <div
                                        key={request.supply_request_id}
                                        onClick={() => toggleSelect(request.supply_request_id)}
                                        className={`group relative flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200 ${
                                            isSelected
                                                ? 'border-red-400 bg-red-50/30 ring-2 ring-red-400/25 shadow-xs'
                                                : request.priority_level?.toUpperCase() === 'URGENT'
                                                ? 'border-red-300 bg-red-50/25 shadow-2xs'
                                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'
                                        }`}
                                    >
                                        <div>
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-2.5">
                                                    <input
                                                        type="checkbox"
                                                        checked={isSelected}
                                                        onChange={() => {}}
                                                        className="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                                    />
                                                    <span className="font-mono text-xs font-bold text-slate-800">
                                                        {request.request_code}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1.5 flex-wrap justify-end">
                                                    {request.priority_level?.toUpperCase() === 'URGENT' && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-black text-white shadow-2xs tracking-wide uppercase">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-white"></span>
                                                            Urgent
                                                        </span>
                                                    )}
                                                    <span className="inline-flex rounded-full border border-rose-200/60 bg-rose-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-700">
                                                        Needs Procurement
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="mt-2 text-xs text-slate-600">
                                                <span className="font-medium text-slate-700">Requester: </span>
                                                {request.requester?.name ? request.requester.name : 'Office Staff'}
                                            </div>

                                            {/* Item preview chips */}
                                            {request.requested_items && request.requested_items.length > 0 && (
                                                <div className="mt-2.5 flex flex-wrap gap-1">
                                                    {request.requested_items.slice(0, 3).map((item, i) => (
                                                        <span
                                                            key={i}
                                                            className="inline-flex items-center rounded-md border border-slate-200/70 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-700"
                                                        >
                                                            {item.item_name}: <strong className="ml-1 text-slate-900">{item.quantity_requested} {item.unit}</strong>
                                                        </span>
                                                    ))}
                                                    {request.requested_items.length > 3 && (
                                                        <span className="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                                                            +{request.requested_items.length - 3} more
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        <div className="mt-3.5 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                                            <span>Needed: {request.needed_by_date || 'ASAP'}</span>
                                            <span className="font-semibold text-slate-700">{request.item_count} item(s)</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Live Merged Demand Drawer */}
                        {selectedRequests.length >= 2 && aggregatedItems.length > 0 && (
                            <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 transition-all">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm">📊</span>
                                        <span className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                            Merged Demand Summary:
                                        </span>
                                        <span className="text-xs font-semibold text-slate-600">
                                            {aggregatedItems.length} unique items across {selectedRequests.length} requests
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setShowPreviewModal(true)}
                                        className="text-xs font-semibold text-red-700 hover:text-red-900 underline"
                                    >
                                        View Full Breakdown ({aggregatedItems.length}) →
                                    </button>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {aggregatedItems.slice(0, 6).map((item, idx) => (
                                        <span
                                            key={idx}
                                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 shadow-2xs"
                                        >
                                            <span className="font-medium text-slate-800">{item.item_name}:</span>
                                            <span className="font-bold text-red-700">{item.total_quantity} {item.unit}</span>
                                        </span>
                                    ))}
                                    {aggregatedItems.length > 6 && (
                                        <span className="inline-flex items-center rounded-lg bg-slate-200/70 px-2 py-1 text-xs font-semibold text-slate-600">
                                            +{aggregatedItems.length - 6} more items
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {requests.data.some((r) => r.priority_level?.toUpperCase() === 'URGENT') && (
                    <div className="rounded-xl border border-red-200 bg-red-50/90 px-4 py-2.5 text-xs text-red-900 flex items-center justify-between shadow-2xs">
                        <div className="flex items-center gap-2">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-white shrink-0">
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <span><strong>Urgent Supply Requests Pinned to Top:</strong> Requests marked urgent are prioritized for immediate review and procurement processing.</span>
                        </div>
                        <span className="rounded-full bg-red-200/80 px-2.5 py-0.5 text-[10px] font-bold text-red-900 border border-red-300">
                            {requests.data.filter((r) => r.priority_level?.toUpperCase() === 'URGENT').length} Urgent Pinned
                        </span>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Needed By</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Stock Assessment</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Progress</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {requests.data.map((request) => {
                                const queueBlockReason = procurementQueueBlockReason(request);
                                const isUrgent = request.priority_level?.toUpperCase() === 'URGENT';

                                return (
                                    <tr key={request.supply_request_id} className={`hover:bg-slate-50/60 ${isUrgent ? 'border-l-4 border-l-red-600 bg-red-50/20' : ''}`}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5 flex-wrap">
                                                <span className="font-mono text-xs text-slate-800 font-semibold">{request.request_code}</span>
                                                {isUrgent && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-red-100 border border-red-300 px-2 py-0.5 text-[10px] font-black text-red-700 shadow-2xs tracking-wide uppercase">
                                                        <span className="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                                        Urgent
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {request.item_count} item(s) • {request.requested_total.toFixed(2)} requested
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <p>{request.office?.name ?? '-'}</p>
                                            <p className="text-xs text-slate-500">{request.requester?.name ?? '-'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{request.needed_by_date ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={request.stock_assessment === 'STOCK_READY' ? stockReadyClass : shortageClass}>
                                                {request.stock_assessment === 'STOCK_READY' ? 'Stock Ready' : 'Needs Procurement'}
                                            </span>
                                            {request.shortage_total > 0 ? (
                                                <p className="mt-1 text-xs text-rose-600">Shortage against available stock: {request.shortage_total.toFixed(2)}</p>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            <p>Status: {displaySupplyRequestStatus(request.status)}</p>
                                            <p className="text-xs text-slate-500">Workflow: {displayWorkflowBucket(request.workflow_bucket)}</p>
                                            <p className="text-xs text-slate-500">Distribution records: {request.distribution_records_count}</p>
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <div className="flex min-w-[220px] flex-col items-start gap-2">
                                                <Link
                                                    href={route('inventory.office-supply-requests.show', {
                                                        supply_request: request.supply_request_id,
                                                        q: filters.q || undefined,
                                                        office_id: filters.office_id || undefined,
                                                        stock_assessment: filters.stock_assessment || undefined,
                                                        workflow_bucket: filters.workflow_bucket || undefined,
                                                        page: filters.page || undefined,
                                                    })}
                                                    className="inline-flex rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                >
                                                    View Full Request
                                                </Link>

                                                {(() => {
                                                    if (request.status === 'COMPLETED' || request.status === 'FULFILLED' || request.status === 'RECEIVED') {
                                                        return (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                                Fulfilled & Completed
                                                            </span>
                                                        );
                                                    }

                                                    if (request.has_purchase_request || request.status === 'FOR_PROCUREMENT') {
                                                        return (
                                                            <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                                                Queued for Procurement
                                                            </span>
                                                        );
                                                    }

                                                    if (request.status === 'REJECTED') {
                                                        return (
                                                            <span className="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">
                                                                Rejected
                                                            </span>
                                                        );
                                                    }

                                                    if (request.stock_assessment === 'STOCK_READY' && request.has_distribution_candidate) {
                                                        return (
                                                            <button
                                                                type="button"
                                                                disabled={processingRequestId === request.supply_request_id}
                                                                onClick={() => processDistribution(request.supply_request_id)}
                                                                className="inline-flex rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 hover:text-red-900 disabled:cursor-not-allowed disabled:opacity-60"
                                                            >
                                                                {processingRequestId === request.supply_request_id ? 'Releasing...' : 'Process Distribution'}
                                                            </button>
                                                        );
                                                    }

                                                    if (request.stock_assessment === 'STOCK_READY') {
                                                        const distReason = distributionBlockReason(request);
                                                        return distReason ? (
                                                            <div className="max-w-[240px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                                                {distReason}
                                                            </div>
                                                        ) : null;
                                                    }

                                                    if (queueBlockReason) {
                                                        return (
                                                            <div className="max-w-[240px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                                                {queueBlockReason}
                                                            </div>
                                                        );
                                                    }

                                                    return (
                                                        <button
                                                            type="button"
                                                            disabled={queueingRequestId === request.supply_request_id}
                                                            onClick={() => queueProcurement(request.supply_request_id)}
                                                            className="inline-flex rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 hover:text-rose-900 disabled:cursor-not-allowed disabled:opacity-60"
                                                        >
                                                            {queueingRequestId === request.supply_request_id ? 'Initiating...' : 'Initiate Purchase Request'}
                                                        </button>
                                                    );
                                                })()}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No office supply requests available for review.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{requests.data.length}</span> office requests
                        </p>
                        {lastRefreshedAt && (
                            <span className="hidden sm:inline text-[11px] text-slate-400">
                                &bull; {isRefreshing ? 'Refreshing...' : `Updated ${lastRefreshedAt.toLocaleTimeString()}`}
                            </span>
                        )}
                    </div>
                    <PaginationNav links={requests.links} preserveState={false} />
                </div>
            </div>
        </div>

            {/* Consolidated PR Preview & Forwarding Modal */}
            <Modal show={showPreviewModal} onClose={() => setShowPreviewModal(false)} maxWidth="2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div className="flex items-center gap-2">
                            <span className="text-xl">📦</span>
                            <div>
                                <h3 className="text-lg font-bold text-slate-900">
                                    Forward Consolidated PR to BAC
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Consolidating for {targetOfficeName} • {selectedRequestIds.size} Supply Request(s)
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowPreviewModal(false)}
                            className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            ✕
                        </button>
                    </div>

                    <div className="mt-4 space-y-4">
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <h5 className="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Selected Source Supply Requests
                            </h5>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {selectedRequests.map((r) => (
                                    <span key={r.supply_request_id} className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-mono font-medium text-slate-800 shadow-sm">
                                        <span className="font-semibold">{r.request_code}</span>
                                        <span className="text-slate-400">({r.item_count} items)</span>
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div>
                            <h5 className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-600">
                                Merged Items to Order (Aggregated Demand)
                            </h5>
                            <div className="max-h-56 overflow-y-auto rounded-lg border border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-100 text-xs font-semibold text-slate-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Item Description</th>
                                            <th className="px-3 py-2 text-center">Unit</th>
                                            <th className="px-3 py-2 text-right">Consolidated Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {aggregatedItems.map((item, idx) => (
                                            <tr key={idx}>
                                                <td className="px-3 py-2 font-medium text-slate-800">{item.item_name}</td>
                                                <td className="px-3 py-2 text-center text-slate-600">{item.unit}</td>
                                                <td className="px-3 py-2 text-right font-bold text-red-700">{item.total_quantity.toFixed(2)}</td>
                                            </tr>
                                        ))}
                                        {aggregatedItems.length === 0 ? (
                                            <tr>
                                                <td colSpan={3} className="px-3 py-4 text-center text-xs text-slate-500">
                                                    No items found in the selected requests.
                                                </td>
                                            </tr>
                                        ) : null}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="rounded-lg border border-red-200 bg-red-50/70 p-3 text-xs text-red-900">
                            <p className="font-semibold">Signatory Transparency Note:</p>
                            <p className="mt-0.5">
                                Submitting will stamp you (GSO Property Custodian) as the Consolidator/Forwarder. The BAC / Procurement Unit will receive this Consolidated PR to initiate supplier canvassing and generate the Consolidated Purchase Order (PO).
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4">
                        <button
                            type="button"
                            onClick={() => setShowPreviewModal(false)}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            disabled={isConsolidating || aggregatedItems.length === 0}
                            onClick={submitConsolidatedPr}
                            className="inline-flex items-center gap-2 rounded-lg bg-red-700 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-800 disabled:opacity-60"
                        >
                            {isConsolidating ? 'Forwarding to BAC...' : 'Confirm & Forward Consolidated PR to BAC'}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

const stockReadyClass = 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
const shortageClass = 'inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700';

function displayWorkflowBucket(bucket: string): string {
    const map: Record<string, string> = {
        COMPLETED: 'Fulfilled & Completed',
        READY_FOR_DISTRIBUTION: 'Ready for Distribution',
        STOCK_READY_AWAITING_RELEASE_CONTEXT: 'Stock Ready Awaiting Release Context',
        QUEUED_FOR_PROCUREMENT: 'Queued for Procurement',
        UNDER_VALIDATION: 'Submitted or Under Validation',
    };

    return map[bucket] ?? humanizeEnum(bucket);
}

function displaySupplyRequestStatus(status: string): string {
    const map: Record<string, string> = {
        DRAFT: 'Draft',
        OPEN: 'Pending Review',
        SUBMITTED: 'Submitted',
        VALIDATING: 'Validating',
        FOR_PROCUREMENT: 'Queued for Procurement',
        READY_FOR_DISTRIBUTION: 'Ready for Distribution',
        COMPLETED: 'Completed',
        FULFILLED: 'Completed',
        RECEIVED: 'Completed',
        REJECTED: 'Rejected',
    };

    return map[status] ?? humanizeEnum(status);
}

function procurementQueueBlockReason(request: RequestRow): string | null {
    if (request.stock_assessment !== 'SHORTAGE_REQUIRES_PROCUREMENT') {
        return null;
    }

    if (request.status === 'COMPLETED' || request.status === 'FULFILLED' || request.status === 'RECEIVED' || request.status === 'FOR_PROCUREMENT' || request.has_purchase_request || request.status === 'REJECTED') {
        return null;
    }

    if (request.status === 'DRAFT') {
        return 'Awaiting office resubmission before GSO can initiate a purchase request.';
    }

    if (request.status !== 'SUBMITTED') {
        return `Cannot initiate while status is ${displaySupplyRequestStatus(request.status)}.`;
    }

    return null;
}

function distributionBlockReason(request: RequestRow): string | null {
    if (request.status === 'COMPLETED' || request.status === 'FULFILLED' || request.status === 'RECEIVED' || request.status === 'FOR_PROCUREMENT' || request.has_purchase_request || request.status === 'REJECTED') {
        return null;
    }

    if (request.status === 'DRAFT') {
        return 'Awaiting office submission before GSO can release this request.';
    }

    if (request.status === 'SUBMITTED' || request.status === 'VALIDATING') {
        return 'Stock-ready request is awaiting a release context.';
    }

    return null;
}



