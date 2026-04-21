import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

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
        status?: string;
        stock_assessment?: string;
        workflow_bucket?: string;
    };
};

export default function OfficeSupplyRequestsIndex({ requests, offices, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [officeId, setOfficeId] = useState(String(filters.office_id ?? ''));
    const [status, setStatus] = useState(filters.status ?? '');
    const [stockAssessment, setStockAssessment] = useState(filters.stock_assessment ?? '');
    const [workflowBucket, setWorkflowBucket] = useState(filters.workflow_bucket ?? '');
    const [queueingRequestId, setQueueingRequestId] = useState<number | null>(null);
    const [processingRequestId, setProcessingRequestId] = useState<number | null>(null);

    useEffect(() => {
        setQ(filters.q ?? '');
        setOfficeId(String(filters.office_id ?? ''));
        setStatus(filters.status ?? '');
        setStockAssessment(filters.stock_assessment ?? '');
        setWorkflowBucket(filters.workflow_bucket ?? '');
    }, [filters.office_id, filters.q, filters.status, filters.stock_assessment, filters.workflow_bucket]);

    const { isRefreshing, lastRefreshedAt } = useSmartRefresh({
        only: ['requests', 'filters'],
        refreshOnFocus: true,
        refreshOnMount: false,
    });

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('inventory.office-supply-requests.index'),
            {
                q,
                office_id: officeId,
                status,
                stock_assessment: stockAssessment,
                workflow_bucket: workflowBucket,
            },
            { preserveState: true, replace: true },
        );
    };

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
                <form onSubmit={applyFilters} className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
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
                    <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        <option value="SUBMITTED">Submitted</option>
                        <option value="VALIDATING">Validating</option>
                        <option value="READY_FOR_DISTRIBUTION">Ready for Distribution</option>
                        <option value="FOR_PROCUREMENT">For Procurement</option>
                    </select>
                    <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Apply
                    </button>
                    <Link href={route('inventory.office-supply-requests.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </Link>
                </form>

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

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Needed By</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Stock Assessment</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Progress</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {requests.data.map((request) => (
                                <tr key={request.supply_request_id} className="hover:bg-slate-50/60">
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
                                    <td className="px-4 py-3 text-right">
                                        {request.stock_assessment === 'STOCK_READY' ? (
                                            <div className="space-y-1">
                                                <button
                                                    type="button"
                                                    disabled={processingRequestId === request.supply_request_id}
                                                    onClick={() => processDistribution(request.supply_request_id)}
                                                    className="text-sm font-semibold text-red-700 hover:text-red-900 disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    {processingRequestId === request.supply_request_id ? 'Opening Supply Distribution...' : 'Process Distribution'}
                                                </button>
                                                {!request.has_distribution_candidate ? (
                                                    <p className="text-xs text-slate-500">Awaiting release context in Supply Distribution.</p>
                                                ) : null}
                                            </div>
                                        ) : request.has_purchase_request ? (
                                            <span className="text-sm font-semibold text-slate-500">Queued for Procurement</span>
                                        ) : (
                                            <button
                                                type="button"
                                                disabled={queueingRequestId === request.supply_request_id}
                                                onClick={() => queueProcurement(request.supply_request_id)}
                                                className="text-sm font-semibold text-rose-700 hover:text-rose-900"
                                            >
                                                {queueingRequestId === request.supply_request_id ? 'Initiating...' : 'Initiate Purchase Request'}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
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

                <div className="flex flex-wrap gap-2">
                    {requests.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            className={`rounded-md px-3 py-2 text-sm ${
                                link.active ? 'bg-red-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
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
        OPEN: 'Pending Review',
        SUBMITTED: 'Submitted',
        VALIDATING: 'Validating',
        FOR_PROCUREMENT: 'Queued for Procurement',
        READY_FOR_DISTRIBUTION: 'Ready for Distribution',
    };

    return map[status] ?? humanizeEnum(status);
}
