import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart } from '@/Components/Executive/Charts';
import { GovDocumentPage, GovDocumentMetadata } from '@/Components/Executive/GovDocumentLayout';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
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

type ProcurementRecordRow = {
    type: 'pr' | 'po';
    record_id: number;
    reference: string;
    route_name:
        | 'executive.procurement-records.purchase-requests.show'
        | 'executive.procurement-records.purchase-orders.show'
        | 'procurement.purchase-requests.show'
        | 'procurement.purchase-orders.show';
    office_name?: string | null;
    status: string;
    amount?: number | null;
    item_count: number;
    created_at?: string | null;
    details?: {
        requester?: string | null;
        supplier?: string | null;
        linked_request?: string | null;
        source_reference?: string | null;
        procurement_method?: string | null;
        remarks?: string | null;
    };
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
        record_type?: 'all' | 'pr' | 'po' | null;
        records_page?: number | null;
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
        procurement_record_count: number;
        purchase_request_item_count: number;
        purchase_order_item_count: number;
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
            data: ProcurementRecordRow[];
            links: Array<{ url: string | null; label: string; active: boolean }>;
        };
        procurement_records?: {
            data: ProcurementRecordRow[];
            links: Array<{ url: string | null; label: string; active: boolean }>;
        };
        procurement_records_export?: ProcurementRecordRow[];
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
        record_type: filters.record_type ?? 'all',
        records_page: filters.records_page ? String(filters.records_page) : '',
    });

    useEffect(() => {
        setForm({
            office_id: filters.office_id ? String(filters.office_id) : '',
            status: filters.status ?? '',
            year: filters.year ?? '',
            month: filters.month ?? '',
            from_date: filters.from_date ?? '',
            to_date: filters.to_date ?? '',
            record_type: filters.record_type ?? 'all',
            records_page: filters.records_page ? String(filters.records_page) : '',
        });
    }, [filters.from_date, filters.month, filters.office_id, filters.record_type, filters.records_page, filters.status, filters.to_date, filters.year]);

    const activeFilters = useMemo(
        () => ({
            office_id: form.office_id,
            status: form.status,
            year: form.year,
            month: form.month,
            from_date: form.from_date,
            to_date: form.to_date,
            record_type: form.record_type,
            records_page: form.records_page,
        }),
        [form.from_date, form.month, form.office_id, form.record_type, form.records_page, form.status, form.to_date, form.year],
    );

    const activeFiltersKey = useMemo(() => serializeProcurementFilters(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => serializeProcurementFilters({
        office_id: filters.office_id ? String(filters.office_id) : '',
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
        record_type: filters.record_type ?? 'all',
        records_page: filters.records_page ? String(filters.records_page) : '',
    }), [filters.from_date, filters.month, filters.office_id, filters.record_type, filters.records_page, filters.status, filters.to_date, filters.year]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('executive.procurement-analytics'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['filters', 'filter_options', 'totals', 'status_breakdown', 'monthly_trends', 'office_comparison', 'recent'],
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

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

    const { auth } = usePage<PageProps>().props;
    const exportSectionId = 'procurement-insights-export-section';
    const procurementRecords = recent.procurement_records ?? recent.purchase_orders;
    const exportProcurementRecords = recent.procurement_records_export ?? procurementRecords.data;
    const procurementExportPages = useMemo(() => chunkRows(exportProcurementRecords, 12), [exportProcurementRecords]);
    const totalPdfPages = 1 + (procurementExportPages.length > 0 ? procurementExportPages.length : 1);

    const baseMetadata: GovDocumentMetadata = {
        title: 'EXECUTIVE PROCUREMENT ANALYTICS & SPENDING REPORT',
        formCode: 'FDP Form - Procurement Spend & Trend Analysis',
        trackingCode: `EBM-PROC-SPEND-${form.year || new Date().getFullYear()}`,
        fiscalYear: form.year || new Date().getFullYear(),
        period: form.year ? `Fiscal Year ${form.year}${form.month ? ` - Month ${form.month}` : ''}` : `Fiscal Year ${new Date().getFullYear()}`,
        moduleScope: 'Purchase Request vs Purchase Order Analytics & Office Spend',
        preparedByName: auth?.user?.name || 'Executive Officer',
        preparedByRole: auth?.user?.role_code || 'Executive Officer',
        preparedAt: new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }),
        approverName: 'HON. JOVILYN D. MAGALONA',
        approverRole: 'Municipal Mayor / Local Chief Executive',
    };

    const exportPdf = async () => {
        await downloadSectionAsPdfById(exportSectionId, {
            title: 'Procurement Insights Report',
            fileName: 'procurement-insights-report.pdf',
            marginMm: 6,
            paperWidthPx: 820,
            orientation: 'portrait',
            excludeSelectors: ['.print-hide', 'nav', 'aside', '[role="navigation"]'],
            revealSelectors: ['.pdf-export-content'],
            pageBreakSelectors: ['.pdf-page'],
            includeDefaultHeading: false,
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

                <section className="rounded-2xl border border-red-200 bg-white p-5 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <select
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.office_id}
                                onChange={(event) => setForm((current) => ({ ...current, office_id: event.target.value, records_page: '' }))}
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
                                onChange={(event) => setForm((current) => ({ ...current, status: event.target.value, records_page: '' }))}
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
                                onMonthChange={(value) => setForm((current) => ({ ...current, month: value, from_date: '', to_date: '', records_page: '' }))}
                                onYearChange={(value) => setForm((current) => ({ ...current, year: value, from_date: '', to_date: '', records_page: '' }))}
                            />

                            <select
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.record_type}
                                onChange={(event) => setForm((current) => ({ ...current, record_type: event.target.value as 'all' | 'pr' | 'po', records_page: '' }))}
                            >
                                <option value="all">All PR and PO records</option>
                                <option value="pr">Purchase Requests only</option>
                                <option value="po">Purchase Orders only</option>
                            </select>
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
                    <Card label="Filtered PR Records" value={totals.purchase_request_count} tone="sky" />
                    <Card label="Filtered PO Records" value={totals.purchase_order_count} tone="emerald" />
                    <Card label="PO Total Amount" value={formatCurrency(totals.purchase_order_total_amount)} tone="amber" />
                    <Card label="PR/PO Line Items" value={totals.purchase_request_item_count + totals.purchase_order_item_count} tone="sky" />
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
                    {procurementRecords.data.length === 0 ? (
                        <p className="px-6 py-8 text-sm text-slate-500">No data in current filter scope</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Record</th>
                                        <th className="px-4 py-2 text-left">Type</th>
                                        <th className="px-4 py-2 text-left">Office</th>
                                        <th className="px-4 py-2 text-left">Status</th>
                                        <th className="px-4 py-2 text-left">Details</th>
                                        <th className="px-4 py-2 text-left">Amount</th>
                                        <th className="px-4 py-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {procurementRecords.data.map((row) => (
                                        <tr key={`${row.type}-${row.record_id}`} className="align-top">
                                            <td className="px-4 py-3 font-semibold text-slate-900">{row.reference}</td>
                                            <td className="px-4 py-3">
                                                <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold uppercase text-red-700">{row.type === 'pr' ? 'PR' : 'PO'}</span>
                                            </td>
                                            <td className="px-4 py-3">{row.office_name ?? 'N/A'}</td>
                                            <td className="px-4 py-3">{row.status}</td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <p>{row.item_count} line item{row.item_count === 1 ? '' : 's'}</p>
                                                <p className="text-xs text-slate-500">{recordDetailText(row)}</p>
                                            </td>
                                            <td className="px-4 py-3">{row.amount == null ? '—' : formatCurrency(row.amount)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link href={route(row.route_name, row.record_id)} className="rounded border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="px-6 py-4">
                                <PaginationNav links={procurementRecords.links} preserveState={false} />
                            </div>
                        </div>
                    )}
                </section>

                <div id={exportSectionId} className="pdf-export-content hidden space-y-6 text-slate-900">
                    {/* Page 1: Analytics & Spend Summary */}
                    <GovDocumentPage
                        metadata={baseMetadata}
                        pageNumber={1}
                        totalPages={totalPdfPages}
                        showLetterhead={true}
                        showSignatories={totalPdfPages === 1}
                        showAttestation={totalPdfPages === 1}
                    >
                        <div className="rounded-lg border border-slate-300 bg-slate-50/50 p-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-2 mb-3">
                                Procurement Activity & Expenditure Totals
                            </h3>
                            <div className="grid grid-cols-3 gap-3">
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Purchase Requests</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{totals.purchase_request_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Purchase Orders</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{totals.purchase_order_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Committed Spend</p>
                                    <p className="mt-1 text-xl font-extrabold text-red-800">{formatCurrency(totals.purchase_order_total_amount)}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    PR vs PO Monthly Volume Trend
                                </h3>
                                <TrendGroupedBarChart
                                    data={monthlyMerged}
                                    firstLabel="Purchase Requests"
                                    secondLabel="Purchase Orders"
                                    emptyText="No data in current filter scope"
                                />
                            </section>

                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Office Procurement Spending Ranking
                                </h3>
                                <HorizontalBarChart
                                    data={officeValueBreakdown}
                                    valueFormatter={formatCurrency}
                                    emptyText="No data in current filter scope"
                                />
                            </section>
                        </div>
                    </GovDocumentPage>

                    {/* Continuation Pages: Detailed Procurement Records */}
                    {procurementExportPages.length === 0 ? (
                        <GovDocumentPage
                            metadata={{
                                ...baseMetadata,
                                title: 'PROCUREMENT TRANSACTION SCHEDULE',
                                moduleScope: 'Itemized Purchase Requests & Purchase Orders',
                            }}
                            pageNumber={2}
                            totalPages={2}
                            showLetterhead={true}
                            showSignatories={true}
                            showAttestation={true}
                        >
                            <p className="py-8 text-center text-sm text-slate-500">No procurement records found within the selected filter criteria.</p>
                        </GovDocumentPage>
                    ) : (
                        procurementExportPages.map((rows, pageIndex) => {
                            const pageNumber = 2 + pageIndex;
                            const isLast = pageNumber === totalPdfPages;

                            return (
                                <GovDocumentPage
                                    key={`procurement-export-page-${pageIndex + 1}`}
                                    metadata={{
                                        ...baseMetadata,
                                        title: 'PROCUREMENT TRANSACTION SCHEDULE',
                                        moduleScope: 'Itemized Purchase Requests & Purchase Orders',
                                    }}
                                    pageNumber={pageNumber}
                                    totalPages={totalPdfPages}
                                    showLetterhead={true}
                                    showSignatories={isLast}
                                    showAttestation={isLast}
                                >
                                    <div className="overflow-x-auto rounded-md border border-slate-300">
                                        <table className="min-w-full table-fixed divide-y divide-slate-300 text-[11px]">
                                            <thead className="bg-slate-100 text-slate-800 font-bold uppercase">
                                                <tr>
                                                    <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Tracking Reference</th>
                                                    <th className="w-[10%] px-3 py-2 text-center border-r border-slate-300">Type</th>
                                                    <th className="w-[32%] px-3 py-2 text-left border-r border-slate-300">Procuring Office</th>
                                                    <th className="w-[20%] px-3 py-2 text-center border-r border-slate-300">Status</th>
                                                    <th className="w-[20%] px-3 py-2 text-right">Amount (PHP)</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200 bg-white">
                                                {rows.map((row) => (
                                                    <tr key={`${row.type}-${row.record_id}`}>
                                                        <td className="px-3 py-2 align-top font-bold text-slate-900 border-r border-slate-200">{row.reference}</td>
                                                        <td className="px-3 py-2 align-top text-center font-semibold text-slate-700 uppercase border-r border-slate-200">{row.type}</td>
                                                        <td className="px-3 py-2 align-top text-slate-800 border-r border-slate-200">{row.office_name ?? 'N/A'}</td>
                                                        <td className="px-3 py-2 align-top text-center text-slate-700 border-r border-slate-200">{row.status}</td>
                                                        <td className="px-3 py-2 align-top text-right font-semibold text-slate-900">{row.amount == null ? '—' : formatCurrency(row.amount)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </GovDocumentPage>
                            );
                        })
                    )}
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

function recordDetailText(row: ProcurementRecordRow): string {
    if (row.type === 'pr') {
        return [row.details?.source_reference, row.details?.requester ? `Requester: ${row.details.requester}` : null]
            .filter(Boolean)
            .join(' · ') || 'Purchase request details available';
    }

    return [row.details?.linked_request, row.details?.supplier ? `Supplier: ${row.details.supplier}` : null]
        .filter(Boolean)
        .join(' · ') || 'Purchase order details available';
}

function serializeProcurementFilters(filters: {
    office_id?: number | string | null;
    status?: string | null;
    year?: string | null;
    month?: string | null;
    from_date?: string | null;
    to_date?: string | null;
    record_type?: string | null;
    records_page?: number | string | null;
}): string {
    return JSON.stringify({
        office_id: String(filters.office_id ?? ''),
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
        record_type: filters.record_type ?? 'all',
        records_page: String(filters.records_page ?? ''),
    });
}

function chunkRows<T>(rows: T[], pageSize: number): T[][] {
    const pages: T[][] = [];
    for (let index = 0; index < rows.length; index += pageSize) {
        pages.push(rows.slice(index, index + pageSize));
    }
    return pages;
}
