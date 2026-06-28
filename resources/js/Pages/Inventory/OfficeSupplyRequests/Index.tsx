import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
    office?: { name?: string | null } | null;
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
};

type PageProps = {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

export default function OfficeSupplyRequestsIndex({ requests, offices, filters }: Props) {
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
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setQ(filters.q ?? '');
        setOfficeId(String(filters.office_id ?? ''));
        setStockAssessment(filters.stock_assessment ?? '');
        setWorkflowBucket(filters.workflow_bucket ?? '');
    }, [filters.office_id, filters.q, filters.stock_assessment, filters.workflow_bucket]);

    const { isRefreshing, lastRefreshedAt } = useSmartRefresh({
        only: ['requests', 'filters'],
        refreshOnFocus: true,
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

    // Eligible requests for consolidation: shortage + submitted + no existing PR
    const consolidatableRequests = requests.data.filter(
        (r) => r.stock_assessment === 'SHORTAGE_REQUIRES_PROCUREMENT' && r.status === 'SUBMITTED' && !r.has_purchase_request,
    );

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

    const currentOfficeId = filters.office_id ?? null;
    const canConsolidate = currentOfficeId !== null && selectedRequestIds.size >= 1;

    const submitConsolidatedPr = () => {
        if (!canConsolidate) return;
        setIsConsolidating(true);
        router.post(
            route('inventory.office-supply-requests.consolidated-queue'),
            {
                office_id: currentOfficeId,
                supply_request_ids: Array.from(selectedRequestIds),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsConsolidating(false);
                    setSelectedRequestIds(new Set());
                },
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Office Supply Requests</h2>
                    <Link href={route('inventory.distribution-records.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Open Supply Distribution
                    </Link>
                </div>
            }
        >
            <Head title="Office Supply Requests" />

            <div className="space-y-4 py-8">
                {flash?.success ? (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                ) : null}
                {flash?.error ? (
                    <div className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                        {flash.error}
                    </div>
                ) : null}

                <div className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Search request code, purpose, or office"
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                    />
                    <select value={officeId} onChange={(event) => setOfficeId(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All offices</option>
                        {offices.map((office) => (
                            <option key={office.id} value={office.id}>
                                {office.name}
                            </option>
                        ))}
                    </select>
                    <select value={workflowBucket} onChange={(event) => setWorkflowBucket(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All workflow buckets</option>
                        <option value="READY_FOR_DISTRIBUTION">Ready for Distribution</option>
                        <option value="STOCK_READY_AWAITING_RELEASE_CONTEXT">Stock Ready Awaiting Release Context</option>
                        <option value="QUEUED_FOR_PROCUREMENT">Queued for Procurement</option>
                        <option value="UNDER_VALIDATION">Submitted or Under Validation</option>
                    </select>
                    <select value={stockAssessment} onChange={(event) => setStockAssessment(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All stock assessments</option>
                        <option value="STOCK_READY">Stock Ready</option>
                        <option value="SHORTAGE_REQUIRES_PROCUREMENT">Needs Procurement</option>
                    </select>
                    <Link href={route('inventory.office-supply-requests.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </Link>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        Review submitted office requests against current stock. Requests with shortages are forwarded to the procurement initiation queue; stock-ready
                        requests proceed to distribution.
                    </p>
                    <p className="mt-2 text-xs text-slate-500">
                        {isRefreshing
                            ? 'Refreshing queue...'
                            : lastRefreshedAt
                            ? `Updated ${lastRefreshedAt.toLocaleTimeString()}`
                            : 'Refreshes when you return to this tab or manually reload the page'}
                    </p>
                </div>

                {currentOfficeId !== null && consolidatableRequests.length > 0 ? (
                    <div className="flex flex-wrap items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-3 shadow-sm">
                        <div className="flex-1">
                            <p className="text-sm font-semibold text-indigo-800">
                                📦 Consolidated PR
                            </p>
                            <p className="mt-0.5 text-xs text-indigo-700">
                                Select shortage requests below to create one consolidated purchase request for this office.
                                {selectedRequestIds.size > 0 ? (
                                    <span className="ml-1 font-bold">{selectedRequestIds.size} request(s) selected.</span>
                                ) : null}
                            </p>
                        </div>
                        <button
                            type="button"
                            disabled={!canConsolidate || isConsolidating}
                            onClick={submitConsolidatedPr}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isConsolidating ? 'Creating...' : `Create Consolidated PR (${selectedRequestIds.size})`}
                        </button>
                    </div>
                ) : null}

                <div className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {currentOfficeId !== null && consolidatableRequests.length > 0 ? (
                                    <th className="px-3 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            checked={selectedRequestIds.size >= consolidatableRequests.length && consolidatableRequests.length > 0}
                                            onChange={toggleSelectAll}
                                            className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            title="Select all eligible requests"
                                        />
                                    </th>
                                ) : null}
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

                                return (
                                <tr key={request.supply_request_id} className="hover:bg-slate-50/60">
                                    {currentOfficeId !== null && consolidatableRequests.length > 0 ? (
                                        <td className="px-3 py-3 text-center">
                                            {consolidatableRequests.some((r) => r.supply_request_id === request.supply_request_id) ? (
                                                <input
                                                    type="checkbox"
                                                    checked={selectedRequestIds.has(request.supply_request_id)}
                                                    onChange={() => toggleSelect(request.supply_request_id)}
                                                    className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                            ) : (
                                                <span className="text-xs text-slate-300">—</span>
                                            )}
                                        </td>
                                    ) : null}
                                    <td className="px-4 py-3">
                                        <p className="font-mono text-xs text-slate-700">{request.request_code}</p>
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

                                            {request.stock_assessment === 'STOCK_READY' && request.has_distribution_candidate ? (
                                                <button
                                                    type="button"
                                                    disabled={processingRequestId === request.supply_request_id}
                                                    onClick={() => processDistribution(request.supply_request_id)}
                                                    className="inline-flex rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 hover:text-red-900 disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    {processingRequestId === request.supply_request_id ? 'Releasing...' : 'Process Distribution'}
                                                </button>
                                            ) : request.stock_assessment === 'STOCK_READY' ? (
                                                <div className="max-w-[240px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                                    {distributionBlockReason(request)}
                                                </div>
                                            ) : request.has_purchase_request ? (
                                                <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Queued for Procurement</span>
                                            ) : queueBlockReason ? (
                                                <div className="max-w-[240px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                                    {queueBlockReason}
                                                </div>
                                            ) : (
                                                <button
                                                    type="button"
                                                    disabled={queueingRequestId === request.supply_request_id}
                                                    onClick={() => queueProcurement(request.supply_request_id)}
                                                    className="inline-flex rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 hover:text-rose-900 disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    {queueingRequestId === request.supply_request_id ? 'Initiating...' : 'Initiate Purchase Request'}
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                                );
                            })}
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={currentOfficeId !== null && consolidatableRequests.length > 0 ? 7 : 6} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No office supply requests available for review.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div>
                    <PaginationNav links={requests.links} preserveState={false} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

const stockReadyClass = 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
const shortageClass = 'inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700';

function displayWorkflowBucket(bucket: string): string {
    const map: Record<string, string> = {
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
        REJECTED: 'Rejected',
    };

    return map[status] ?? humanizeEnum(status);
}

function procurementQueueBlockReason(request: RequestRow): string | null {
    if (request.stock_assessment !== 'SHORTAGE_REQUIRES_PROCUREMENT') {
        return null;
    }

    if (request.status !== 'SUBMITTED') {
        if (request.status === 'DRAFT') {
            return 'Awaiting office resubmission before GSO can initiate a purchase request.';
        }

        return `Cannot initiate while status is ${displaySupplyRequestStatus(request.status)}.`;
    }

    return null;
}

function distributionBlockReason(request: RequestRow): string {
    if (request.status !== 'SUBMITTED') {
        if (request.status === 'DRAFT') {
            return 'Awaiting office submission before GSO can release this request.';
        }

        return `Cannot release while status is ${displaySupplyRequestStatus(request.status)}.`;
    }

    return 'Stock-ready request is awaiting a release context.';
}



