import { ExecutiveChartCard, GroupedComparisonChart, HorizontalBarChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type OfficeOption = { id: number; name: string };
type BreakdownRow = { status: string; total: number };
type TrendRow = { month: string; total: number };

type PurchaseRequestRow = {
    purchase_request_id: number;
    office?: { id: number; name: string } | null;
    status: string;
    created_at: string;
};

type PurchaseOrderRow = {
    purchase_order_id: number;
    status: string;
    total_amount: number;
    created_at: string;
    purchase_request?: {
        purchase_request_id: number;
        office?: { id: number; name: string } | null;
    } | null;
};

type Props = {
    filters: {
        office_id?: number | null;
        pr_status?: string | null;
        po_status?: string | null;
        from_date?: string | null;
        to_date?: string | null;
    };
    filter_options: {
        offices: OfficeOption[];
        pr_statuses: string[];
        po_statuses: string[];
    };
    totals: {
        purchase_request_count: number;
        purchase_order_count: number;
        purchase_order_total_amount: number;
    };
    status_breakdown: {
        purchase_requests: BreakdownRow[];
        purchase_orders: BreakdownRow[];
    };
    monthly_trends: {
        purchase_requests: TrendRow[];
        purchase_orders: TrendRow[];
    };
    office_comparison: Array<{
        office_id: number;
        office_name: string;
        purchase_request_count: number;
        purchase_order_count: number;
        purchase_order_total_amount: number;
    }>;
    recent: {
        purchase_requests: PurchaseRequestRow[];
        purchase_orders: PurchaseOrderRow[];
    };
};

export default function ProcurementAnalyticsIndex({
    filters,
    filter_options,
    totals,
    status_breakdown,
    monthly_trends,
    office_comparison,
    recent,
}: Props) {
    const [form, setForm] = useState({
        office_id: filters.office_id ? String(filters.office_id) : '',
        pr_status: filters.pr_status ?? '',
        po_status: filters.po_status ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('executive.procurement-analytics'), form, { preserveState: true, replace: true });
    };

    const monthlyMerged = mergeTrend(monthly_trends.purchase_requests, monthly_trends.purchase_orders);
    const officeValueBreakdown = office_comparison
        .map((row) => ({
            label: row.office_name,
            value: row.purchase_order_total_amount,
        }))
        .sort((a, b) => b.value - a.value);

    return (
        <AuthenticatedLayout header="Procurement Analytics">
            <Head title="Procurement Analytics" />

            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Executive Controls</p>
                            <h3 className="text-xl font-semibold text-slate-900">Analytics Filter Bar</h3>
                            <p className="mt-1 text-sm text-slate-600">Adjust office, workflow states, and date scope before reading chart outputs.</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.get(route('executive.procurement-analytics'))}
                            className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 transition hover:border-red-500 hover:bg-red-50"
                        >
                            Reset
                        </button>
                    </div>

                    <form onSubmit={onSubmit} className="grid gap-3 md:grid-cols-6">
                        <select
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.office_id}
                            onChange={(event) => setForm((current) => ({ ...current, office_id: event.target.value }))}
                        >
                            <option value="">All offices</option>
                            {filter_options.offices.map((office) => (
                                <option key={office.id} value={office.id}>
                                    {office.name}
                                </option>
                            ))}
                        </select>

                        <select
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.pr_status}
                            onChange={(event) => setForm((current) => ({ ...current, pr_status: event.target.value }))}
                        >
                            <option value="">All PR statuses</option>
                            {filter_options.pr_statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>

                        <select
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.po_status}
                            onChange={(event) => setForm((current) => ({ ...current, po_status: event.target.value }))}
                        >
                            <option value="">All PO statuses</option>
                            {filter_options.po_statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>

                        <input
                            type="date"
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.from_date}
                            onChange={(event) => setForm((current) => ({ ...current, from_date: event.target.value }))}
                        />
                        <input
                            type="date"
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.to_date}
                            onChange={(event) => setForm((current) => ({ ...current, to_date: event.target.value }))}
                        />

                        <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-[0_8px_18px_rgba(185,28,28,0.2)] hover:bg-red-800">
                            Apply
                        </button>
                    </form>
                </section>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card label="Purchase Requests" value={totals.purchase_request_count} tone="sky" />
                    <Card label="Purchase Orders" value={totals.purchase_order_count} tone="emerald" />
                    <Card label="PO Total Amount" value={formatCurrency(totals.purchase_order_total_amount)} tone="amber" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-12">
                        <ExecutiveChartCard
                            title="Monthly Procurement Trend"
                            subtitle="Primary momentum view for requests and orders over time"
                            eyebrow="Primary Trend"
                            tone="critical"
                        >
                            <GroupedComparisonChart
                                data={monthlyMerged}
                                firstLabel="Purchase Requests"
                                secondLabel="Purchase Orders"
                                emptyText="No monthly trend data for the selected filters."
                            />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard title="Purchase Request Status" subtitle="Real-time request lifecycle distribution" eyebrow="Status Mix" tone="default">
                        <HorizontalBarChart
                            data={status_breakdown.purchase_requests.map((row) => ({ label: row.status || 'N/A', value: row.total }))}
                            emptyText="No purchase request status data for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard title="Purchase Order Status" subtitle="Order progression across filtered records" eyebrow="Status Mix" tone="default">
                        <HorizontalBarChart
                            data={status_breakdown.purchase_orders.map((row) => ({ label: row.status || 'N/A', value: row.total }))}
                            emptyText="No purchase order status data for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                    <ExecutiveChartCard title="Office Workflow Comparison" subtitle="Office-level request and order throughput" eyebrow="Cross-Office" tone="default">
                        <GroupedComparisonChart
                            data={office_comparison.map((row) => ({
                                label: row.office_name,
                                first: row.purchase_request_count,
                                second: row.purchase_order_count,
                            }))}
                            firstLabel="Requests"
                            secondLabel="Orders"
                            emptyText="No office-level procurement data for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard title="Office Value Concentration" subtitle="Purchase order amount concentration by office" eyebrow="Financial Focus" tone="warning">
                        <HorizontalBarChart
                            data={officeValueBreakdown}
                            valueFormatter={formatCurrency}
                            emptyText="No office amount concentration data for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h3 className="text-xl font-semibold text-slate-800">Recent Purchase Requests</h3>
                            <p className="mt-1 text-sm text-slate-500">Secondary evidence list after executive visual summaries.</p>
                        </div>
                        {recent.purchase_requests.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-slate-500">No purchase requests found for the selected filters.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left">ID</th>
                                            <th className="px-4 py-2 text-left">Office</th>
                                            <th className="px-4 py-2 text-left">Status</th>
                                            <th className="px-4 py-2 text-left">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recent.purchase_requests.map((row) => (
                                            <tr key={row.purchase_request_id}>
                                                <td className="px-4 py-2">{row.purchase_request_id}</td>
                                                <td className="px-4 py-2">{row.office?.name ?? 'N/A'}</td>
                                                <td className="px-4 py-2">{row.status}</td>
                                                <td className="px-4 py-2">{new Date(row.created_at).toLocaleDateString()}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h3 className="text-xl font-semibold text-slate-800">Recent Purchase Orders</h3>
                            <p className="mt-1 text-sm text-slate-500">Secondary evidence list after executive visual summaries.</p>
                        </div>
                        {recent.purchase_orders.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-slate-500">No purchase orders found for the selected filters.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left">ID</th>
                                            <th className="px-4 py-2 text-left">Office</th>
                                            <th className="px-4 py-2 text-left">Status</th>
                                            <th className="px-4 py-2 text-left">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recent.purchase_orders.map((row) => (
                                            <tr key={row.purchase_order_id}>
                                                <td className="px-4 py-2">{row.purchase_order_id}</td>
                                                <td className="px-4 py-2">{row.purchase_request?.office?.name ?? 'N/A'}</td>
                                                <td className="px-4 py-2">{row.status}</td>
                                                <td className="px-4 py-2">{formatCurrency(row.total_amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function mergeTrend(requestTrend: TrendRow[], orderTrend: TrendRow[]) {
    const monthMap = new Map<string, { pr: number; po: number }>();

    requestTrend.forEach((row) => {
        const existing = monthMap.get(row.month) ?? { pr: 0, po: 0 };
        monthMap.set(row.month, { ...existing, pr: row.total });
    });

    orderTrend.forEach((row) => {
        const existing = monthMap.get(row.month) ?? { pr: 0, po: 0 };
        monthMap.set(row.month, { ...existing, po: row.total });
    });

    return [...monthMap.entries()]
        .sort(([monthA], [monthB]) => monthA.localeCompare(monthB))
        .map(([month, totals]) => ({
            label: formatMonth(month),
            first: totals.pr,
            second: totals.po,
        }));
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function Card({ label, value, tone }: { label: string; value: string | number; tone: 'sky' | 'emerald' | 'amber' }) {
    const toneClass: Record<typeof tone, string> = {
        sky: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900',
        emerald: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900',
        amber: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900',
    };

    return (
        <div className={`rounded-2xl border p-6 shadow-[0_8px_20px_rgba(15,23,42,0.06)] ${toneClass[tone]}`}>
            <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-2 text-4xl font-bold text-red-700">{value}</p>
        </div>
    );
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
}
