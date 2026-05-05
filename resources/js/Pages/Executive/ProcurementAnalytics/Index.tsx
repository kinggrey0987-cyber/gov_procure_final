import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart } from '@/Components/Executive/Charts';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
        status?: string | null;
        pr_status?: string | null;
        po_status?: string | null;
        year?: string | null;
        month?: string | null;
        from_date?: string | null;
        to_date?: string | null;
        po_page?: number | null;
    };
    filter_options: {
        offices: OfficeOption[];
        statuses: string[];
        pr_statuses: string[];
        po_statuses: string[];
        years: number[];
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
        purchase_orders: {
            data: PurchaseOrderRow[];
            links: Array<{ url: string | null; label: string; active: boolean }>;
        };
        purchase_orders_export: PurchaseOrderRow[];
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
    const printSectionId = 'procurement-insights-print-section';

    const [form, setForm] = useState({
        office_id: filters.office_id ? String(filters.office_id) : '',
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
        po_page: filters.po_page ? String(filters.po_page) : '',
    });

    useEffect(() => {
        setForm({
            office_id: filters.office_id ? String(filters.office_id) : '',
            status: filters.status ?? '',
            year: filters.year ?? '',
            month: filters.month ?? '',
            from_date: filters.from_date ?? '',
            to_date: filters.to_date ?? '',
            po_page: filters.po_page ? String(filters.po_page) : '',
        });
    }, [filters.from_date, filters.month, filters.office_id, filters.po_page, filters.status, filters.to_date, filters.year]);

    const activeFilters = useMemo(
        () => ({
            office_id: form.office_id,
            status: form.status,
            year: form.year,
            month: form.month,
            from_date: form.from_date,
            to_date: form.to_date,
            po_page: form.po_page,
        }),
        [form.from_date, form.month, form.office_id, form.po_page, form.status, form.to_date, form.year],
    );

    useEffect(() => {
        router.get(route('executive.procurement-analytics'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['filters', 'filter_options', 'totals', 'status_breakdown', 'monthly_trends', 'office_comparison', 'recent'],
        });
    }, [activeFilters]);

    const monthlyMerged = mergeTrend(monthly_trends.purchase_requests, monthly_trends.purchase_orders);
    const officeValueBreakdown = office_comparison
        .map((row) => ({
            label: row.office_name,
            value: row.purchase_order_total_amount,
        }))
        .sort((a, b) => b.value - a.value);

    const statusMixPr = status_breakdown.purchase_requests.map((row) => ({
        label: row.status || 'N/A',
        value: row.total,
    }));

    const statusMixPo = status_breakdown.purchase_orders.map((row) => ({
        label: row.status || 'N/A',
        value: row.total,
    }));

    const crossOfficeActivity = office_comparison.filter((row) => row.purchase_request_count > 0 || row.purchase_order_count > 0).length;

    const exportCsv = () => {
        const lines = [
            ['metric', 'value'],
            ['purchase_requests', String(totals.purchase_request_count)],
            ['purchase_orders', String(totals.purchase_order_count)],
            ['purchase_order_total_amount', String(totals.purchase_order_total_amount)],
            ['active_offices', String(crossOfficeActivity)],
            [''],
            ['office', 'po_amount'],
            ...officeValueBreakdown.map((row) => [row.label, String(row.value)]),
        ];

        downloadCsv(lines, 'procurement-insights.csv');
    };

    const exportSectionId = 'procurement-insights-export-section';
    const exportPurchaseOrders = recent.purchase_orders_export ?? recent.purchase_orders.data;

    const exportPdf = async () => {
        await downloadSectionAsPdfById(exportSectionId, {
            title: 'Procurement Insights',
            fileName: 'procurement-insights.pdf',
            excludeSelectors: ['.print-hide', 'nav', 'aside', '[role="navigation"]'],
        });
    };

    return (
        <AuthenticatedLayout header="Procurement Insights">
            <Head title="Procurement Insights" />

            <div id={printSectionId} className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Procurement Insights</h2>
                        <p className="text-sm text-slate-600">Executive procurement signals</p>
                    </div>
                    <div className="print-hide flex items-center gap-2">
                        <button type="button" onClick={exportPdf} className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Export PDF
                        </button>
                        <button type="button" onClick={exportCsv} className="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Export CSV
                        </button>
                    </div>
                </section>

                <div id={exportSectionId} className="space-y-6">
                    <div className="grid gap-6 xl:grid-cols-12">
                        <div className="xl:col-span-8">
                            <ExecutiveChartCard title="PR vs PO Trend" subtitle="Monthly movement" tone="critical">
                                <TrendGroupedBarChart data={monthlyMerged} firstLabel="Purchase Requests" secondLabel="Purchase Orders" emptyText="No data in current filter scope" />
                            </ExecutiveChartCard>
                        </div>
                        <div className="xl:col-span-4">
                            <ExecutiveChartCard title="Top Procurement Concentration" subtitle="Office spending ranking" tone="neutral">
                                <HorizontalBarChart data={officeValueBreakdown} valueFormatter={formatCurrency} emptyText="No data in current filter scope" />
                            </ExecutiveChartCard>
                        </div>
                        <div className="xl:col-span-6">
                            <ExecutiveChartCard title="PR Status Mix" subtitle="Request lifecycle" tone="default">
                                <DonutChart data={statusMixPr} centerLabel="PR" emptyText="No data in current filter scope" />
                            </ExecutiveChartCard>
                        </div>
                        <div className="xl:col-span-6">
                            <ExecutiveChartCard title="PO Status Mix" subtitle="Order progression" tone="default">
                                <DonutChart data={statusMixPo} centerLabel="PO" emptyText="No data in current filter scope" />
                            </ExecutiveChartCard>
                        </div>
                    </div>

                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h3 className="text-xl font-semibold text-slate-800">All Filtered Procurement Records</h3>
                            <p className="mt-1 text-sm text-slate-600">Includes all purchase orders in the current filter scope, not only the visible page.</p>
                        </div>
                        {exportPurchaseOrders.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left">PO</th>
                                            <th className="px-4 py-2 text-left">Office</th>
                                            <th className="px-4 py-2 text-left">Status</th>
                                            <th className="px-4 py-2 text-left">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {exportPurchaseOrders.map((row) => (
                                            <tr key={row.purchase_order_id}>
                                                <td className="px-4 py-2">PO-{row.purchase_order_id}</td>
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

                <section className="rounded-2xl border border-red-200 bg-white p-5 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <select
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.office_id}
                                onChange={(event) => setForm((current) => ({ ...current, office_id: event.target.value, po_page: '' }))}
                            >
                                <option value="">All offices</option>
                                {filter_options.offices.map((office) => (
                                    <option key={office.id} value={office.id}>
                                        {office.name}
                                    </option>
                                ))}
                            </select>

                            <select
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.status}
                                onChange={(event) => setForm((current) => ({ ...current, status: event.target.value, po_page: '' }))}
                            >
                                <option value="">All statuses</option>
                                {filter_options.statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>

                            <MonthFilterSelect
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                month={form.month}
                                year={form.year}
                                yearOptions={filter_options.years}
                                onMonthChange={(value) => setForm((current) => ({ ...current, month: value, from_date: '', to_date: '', po_page: '' }))}
                                onYearChange={(value) => setForm((current) => ({ ...current, year: value, from_date: '', to_date: '', po_page: '' }))}
                            />
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.procurement-analytics'))}
                                className="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 transition hover:border-red-500 hover:bg-red-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card label="Purchase Requests" value={totals.purchase_request_count} tone="sky" />
                    <Card label="Purchase Orders" value={totals.purchase_order_count} tone="emerald" />
                    <Card label="PO Total Amount" value={formatCurrency(totals.purchase_order_total_amount)} tone="amber" />
                    <Card label="Active Offices" value={crossOfficeActivity} tone="sky" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                        <ExecutiveChartCard
                            title="PR vs PO Trend"
                            subtitle="Monthly movement"
                            tone="critical"
                        >
                            <TrendGroupedBarChart
                                data={monthlyMerged}
                                firstLabel="Purchase Requests"
                                secondLabel="Purchase Orders"
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard title="Top Procurement Concentration" subtitle="Office spending ranking" tone="neutral">
                        <HorizontalBarChart
                            data={officeValueBreakdown}
                            valueFormatter={formatCurrency}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard title="PR Status Mix" subtitle="Request lifecycle" tone="default">
                        <DonutChart
                            data={statusMixPr}
                            centerLabel="PR"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard title="PO Status Mix" subtitle="Order progression" tone="default">
                        <DonutChart
                            data={statusMixPo}
                            centerLabel="PO"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-800">Latest Procurement Records</h3>
                    </div>
                    {recent.purchase_orders.data.length === 0 ? (
                        <p className="px-6 py-8 text-sm text-slate-500">No data in current filter scope</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left">PO</th>
                                        <th className="px-4 py-2 text-left">Office</th>
                                        <th className="px-4 py-2 text-left">Status</th>
                                        <th className="px-4 py-2 text-left">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recent.purchase_orders.data.map((row) => (
                                        <tr key={row.purchase_order_id}>
                                            <td className="px-4 py-2">PO-{row.purchase_order_id}</td>
                                            <td className="px-4 py-2">{row.purchase_request?.office?.name ?? 'N/A'}</td>
                                            <td className="px-4 py-2">{row.status}</td>
                                            <td className="px-4 py-2">{formatCurrency(row.total_amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="px-6 py-4">
                                <PaginationNav links={recent.purchase_orders.links} preserveState={false} />
                            </div>
                        </div>
                    )}
                </section>
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

function downloadCsv(rows: string[][], fileName: string): void {
    const csv = rows.map((row) => row.map((column) => `"${String(column).replaceAll('"', '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
}
