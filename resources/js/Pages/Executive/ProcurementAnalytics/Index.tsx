import { DonutChart, ExecutiveChartCard, HorizontalBarChart, SpendPoint, SpendTrendBarChart, TrendGroupedBarChart, TrendLineChart } from '@/Components/Executive/Charts';
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
        spending?: Array<{ month: string; total: number; order_count?: number }>;
    };
    office_comparison: Array<{
        office_id: number;
        office_name: string;
        purchase_request_count: number;
        purchase_order_count: number;
        purchase_order_total_amount: number;
        spending_share_percentage?: number;
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

    const [trendView, setTrendView] = useState<'spending' | 'volume'>('spending');

    const isSingleYearScope = Boolean(form.year);

    const spendTrendPoints: SpendPoint[] = useMemo(() => {
        if (monthly_trends.spending && monthly_trends.spending.length > 0) {
            const allMonths = monthly_trends.spending.map((row) => row.month);
            const uniqueYears = [...new Set(allMonths.map((m) => m.split('-')[0]))];
            const isSingleYear = isSingleYearScope || uniqueYears.length <= 1;

            return monthly_trends.spending.map((row) => ({
                label: formatMonth(row.month, isSingleYear),
                value: row.total,
                orderCount: row.order_count,
                rawMonth: row.month,
            }));
        }
        return [];
    }, [monthly_trends.spending, isSingleYearScope]);

    const totalProcurementSpend = useMemo(
        () => office_comparison.reduce((sum, o) => sum + o.purchase_order_total_amount, 0),
        [office_comparison],
    );

    const maxOfficeSpend = useMemo(
        () => Math.max(...office_comparison.map((o) => o.purchase_order_total_amount), 1),
        [office_comparison],
    );

    const monthlyMerged = useMemo(() => {
        return mergeTrend(monthly_trends.purchase_requests, monthly_trends.purchase_orders, isSingleYearScope);
    }, [monthly_trends.purchase_requests, monthly_trends.purchase_orders, isSingleYearScope]);

    const totalActivityTrend = useMemo(() => {
        return monthlyMerged.map((row) => ({
            label: row.label,
            value: row.first + row.second,
        }));
    }, [monthlyMerged]);

    const [volumeMetric, setVolumeMetric] = useState<'combined' | 'pr' | 'po'>('combined');

    const prActivityTrend = useMemo(() => {
        return monthlyMerged.map((row) => ({
            label: row.label,
            value: row.first,
        }));
    }, [monthlyMerged]);

    const poActivityTrend = useMemo(() => {
        return monthlyMerged.map((row) => ({
            label: row.label,
            value: row.second,
        }));
    }, [monthlyMerged]);

    const activeVolumeTrend = useMemo(() => {
        if (volumeMetric === 'pr') return prActivityTrend;
        if (volumeMetric === 'po') return poActivityTrend;
        return totalActivityTrend;
    }, [volumeMetric, prActivityTrend, poActivityTrend, totalActivityTrend]);

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
            ['rank', 'office', 'po_amount', 'spending_share_percent', 'po_count', 'pr_count'],
            ...office_comparison.map((row, idx) => [
                String(idx + 1),
                row.office_name,
                String(row.purchase_order_total_amount),
                String(row.spending_share_percentage ?? ''),
                String(row.purchase_order_count),
                String(row.purchase_request_count),
            ]),
            [''],
            ['month', 'procurement_spend_php', 'po_count'],
            ...spendTrendPoints.map((row) => [
                row.label,
                String(row.value),
                String(row.orderCount ?? 0),
            ]),
        ];

        downloadCsv(lines, 'procurement-insights.csv');
    };

    const { auth } = usePage<PageProps>().props;
    const exportSectionId = 'procurement-insights-export-section';
    const procurementRecords = recent.procurement_records ?? recent.purchase_orders;
    const exportProcurementRecords: ProcurementRecordRow[] = recent.procurement_records_export ?? (procurementRecords.data as unknown as ProcurementRecordRow[]);
    const procurementExportPages = useMemo(() => chunkRows(exportProcurementRecords.slice(0, 36), 12), [exportProcurementRecords]);
    const totalPdfPages = 1 + (procurementExportPages.length > 0 ? procurementExportPages.length : 1);

    const selectedOfficeName = useMemo(() => {
        if (!form.office_id) return 'All Offices';
        const match = filter_options.offices.find((o) => String(o.id) === String(form.office_id));
        return match ? match.name : `Office #${form.office_id}`;
    }, [filter_options.offices, form.office_id]);

    const filterScopeSummary = useMemo(() => {
        const parts: string[] = [];
        parts.push(`Office: ${selectedOfficeName}`);
        parts.push(`Fiscal Year: ${form.year || 'All Years'}`);
        if (form.status) parts.push(`Status: ${form.status}`);
        if (form.record_type && form.record_type !== 'all') parts.push(`Scope: ${form.record_type.toUpperCase()}`);
        if (form.month) parts.push(`Month: ${form.month}`);
        if (form.from_date || form.to_date) parts.push(`Date: ${form.from_date || 'Start'} to ${form.to_date || 'Present'}`);
        return parts.join(' | ');
    }, [selectedOfficeName, form.year, form.status, form.record_type, form.month, form.from_date, form.to_date]);

    const baseMetadata: GovDocumentMetadata = {
        title: 'EXECUTIVE PROCUREMENT ANALYTICS & SPENDING REPORT',
        formCode: 'FDP Form - Procurement Spend & Trend Analysis',
        trackingCode: `EBM-PROC-SPEND-${form.year || new Date().getFullYear()}`,
        fiscalYear: form.year || new Date().getFullYear(),
        period: form.year ? `Fiscal Year ${form.year}${form.month ? ` - Month ${form.month}` : ''}` : `Fiscal Year ${new Date().getFullYear()}`,
        moduleScope: `Procurement & Spend Analytics (${selectedOfficeName})`,
        filterScopeSummary,
        preparedByName: auth?.user?.name || 'Mayor Jeron',
        preparedByRole: auth?.user?.role_code || 'Municipal Mayor / Local Chief Executive',
        preparedAt: new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }),
    };

    const [isExportingPdf, setIsExportingPdf] = useState(false);

    const exportPdf = async () => {
        if (isExportingPdf) return;
        setIsExportingPdf(true);
        try {
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
        } finally {
            setIsExportingPdf(false);
        }
    };

    return (
        <AuthenticatedLayout
            header="Procurement Insights"
            headerActions={
                <div className="print-hide flex items-center gap-2">
                    <button
                        type="button"
                        onClick={exportPdf}
                        disabled={isExportingPdf}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5 text-red-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                        </svg>
                        {isExportingPdf ? 'Generating PDF...' : 'Export PDF'}
                    </button>
                    <button
                        type="button"
                        onClick={exportCsv}
                        className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                        Export CSV
                    </button>
                </div>
            }
        >
            <Head title="Procurement Insights" />

            <div id={printSectionId} className="space-y-4">

                <section className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            <select
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                month={form.month}
                                year={form.year}
                                yearOptions={filter_options.years}
                                onMonthChange={(value) => setForm((current) => ({ ...current, month: value, from_date: '', to_date: '', records_page: '' }))}
                                onYearChange={(value) => setForm((current) => ({ ...current, year: value, from_date: '', to_date: '', records_page: '' }))}
                            />

                            <select
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                                className="h-8 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </section>

                {/* Slim Executive Telemetry Strip - Interactive Drill-Downs */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <button
                            type="button"
                            onClick={() => {
                                setForm((curr) => ({ ...curr, record_type: 'pr', records_page: '' }));
                                document.getElementById('latest-procurement-records-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-slate-50 group cursor-pointer"
                            title="Filter by Purchase Requests"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Filtered PRs</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight group-hover:text-red-700">{totals.purchase_request_count}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setForm((curr) => ({ ...curr, record_type: 'po', records_page: '' }));
                                document.getElementById('latest-procurement-records-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-slate-50 group cursor-pointer"
                            title="Filter by Purchase Orders"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Filtered POs</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight group-hover:text-red-700">{totals.purchase_order_count}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                document.getElementById('latest-procurement-records-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-slate-50 group cursor-pointer"
                            title="Scroll to Procurement Records"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Total Spend</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight truncate group-hover:text-red-700">{formatCurrency(totals.purchase_order_total_amount)}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                document.getElementById('latest-procurement-records-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-slate-50 group cursor-pointer"
                            title="Scroll to Procurement Records"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Line Items</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight group-hover:text-red-700">{totals.purchase_request_item_count + totals.purchase_order_item_count}</p>
                            </div>
                        </button>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-12 items-stretch">
                    {/* Hero Priority: Procurement Spending & Velocity Trend (Full Width xl:col-span-12) */}
                    <div className="xl:col-span-12 flex flex-col">
                        <ExecutiveChartCard
                            title={trendView === 'spending' ? 'Procurement Spending Trend' : 'Procurement Velocity & Document Trend'}
                            subtitle={
                                trendView === 'spending'
                                    ? 'Monthly financial procurement expenditure across fiscal periods'
                                    : volumeMetric === 'combined'
                                        ? `Total Combined Throughput: ${totals.purchase_request_count} Purchase Requests + ${totals.purchase_order_count} Purchase Orders (${totals.purchase_request_count + totals.purchase_order_count} total documents)`
                                        : volumeMetric === 'pr'
                                            ? `Purchase Requests requisition throughput (${totals.purchase_request_count} total PRs)`
                                            : `Purchase Orders fulfillment throughput (${totals.purchase_order_count} total POs)`
                            }
                            eyebrow={trendView === 'spending' ? 'Financial Signal' : 'Operational Signal'}
                            tone="default"
                            className="h-full shadow-sm hover:shadow-md"
                            action={
                                <div className="flex flex-wrap items-center gap-2">
                                    {trendView === 'volume' && (
                                        <div className="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-0.5 text-xs font-semibold">
                                            <button
                                                type="button"
                                                onClick={() => setVolumeMetric('combined')}
                                                className={`rounded-md px-2 py-1 transition ${
                                                    volumeMetric === 'combined'
                                                        ? 'bg-white text-slate-900 shadow-xs font-bold'
                                                        : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                            >
                                                Combined (PR + PO)
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setVolumeMetric('pr')}
                                                className={`rounded-md px-2 py-1 transition ${
                                                    volumeMetric === 'pr'
                                                        ? 'bg-white text-red-700 shadow-xs font-bold'
                                                        : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                            >
                                                PRs ({totals.purchase_request_count})
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setVolumeMetric('po')}
                                                className={`rounded-md px-2 py-1 transition ${
                                                    volumeMetric === 'po'
                                                        ? 'bg-white text-amber-700 shadow-xs font-bold'
                                                        : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                            >
                                                POs ({totals.purchase_order_count})
                                            </button>
                                        </div>
                                    )}
                                    <div className="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-0.5 text-xs font-semibold">
                                        <button
                                            type="button"
                                            onClick={() => setTrendView('spending')}
                                            className={`rounded-md px-2.5 py-1 transition ${
                                                trendView === 'spending'
                                                    ? 'bg-white text-red-700 shadow-sm font-bold'
                                                    : 'text-slate-600 hover:text-slate-900'
                                            }`}
                                        >
                                            ₱ Expenditure (PHP)
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setTrendView('volume')}
                                            className={`rounded-md px-2.5 py-1 transition ${
                                                trendView === 'volume'
                                                    ? 'bg-white text-red-700 shadow-sm font-bold'
                                                    : 'text-slate-600 hover:text-slate-900'
                                            }`}
                                        >
                                            📊 Document Velocity
                                        </button>
                                    </div>
                                </div>
                            }
                        >
                            {trendView === 'spending' ? (
                                <SpendTrendBarChart
                                    data={spendTrendPoints}
                                    emptyText="No procurement expenditure recorded in current filter scope"
                                />
                            ) : (
                                <TrendLineChart
                                    data={activeVolumeTrend}
                                    emptyText="No procurement document volume recorded in current filter scope"
                                    valueFormatter={(val) => `${val} docs`}
                                />
                            )}
                        </ExecutiveChartCard>
                    </div>

                    {/* Departmental Procurement Spending Concentration (xl:col-span-6) */}
                    <div className="xl:col-span-6 flex flex-col">
                        <ExecutiveChartCard
                            title="Top Procurement Concentration"
                            subtitle="Departmental budget utilization and expenditure ranking"
                            eyebrow="Budget Priority"
                            tone="critical"
                            className="h-full"
                        >
                            {office_comparison.length === 0 ? (
                                <p className="py-8 text-center text-sm text-slate-500">No office spending data in current filter scope</p>
                            ) : (
                                <div className="flex-1 flex flex-col justify-between gap-2.5">
                                    {office_comparison.map((office, idx) => {
                                        const percent = office.spending_share_percentage ?? (totalProcurementSpend > 0 ? Math.round((office.purchase_order_total_amount / totalProcurementSpend) * 100) : 0);
                                        const barWidth = Math.max((office.purchase_order_total_amount / maxOfficeSpend) * 100, office.purchase_order_total_amount > 0 ? 6 : 0);

                                        const gradients = [
                                            'from-red-600 to-rose-500',
                                            'from-amber-600 to-amber-500',
                                            'from-indigo-600 to-blue-500',
                                            'from-teal-600 to-emerald-500',
                                            'from-purple-600 to-pink-500',
                                            'from-slate-600 to-slate-500',
                                        ];
                                        const barGradient = gradients[idx % gradients.length];

                                        const rankMedals = ['#1', '#2', '#3', '#4', '#5'];
                                        const rankBadgeStyle = idx === 0
                                            ? 'bg-amber-100 text-amber-900 border-amber-300 ring-1 ring-amber-200'
                                            : idx === 1
                                            ? 'bg-slate-100 text-slate-700 border-slate-300'
                                            : idx === 2
                                            ? 'bg-amber-50 text-amber-800 border-amber-200'
                                            : 'bg-slate-50 text-slate-600 border-slate-200';

                                        return (
                                            <div
                                                key={office.office_id}
                                                className="group rounded-xl border border-slate-200 bg-white p-3 transition hover:border-red-300 hover:shadow-sm"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div className="flex items-start gap-2.5 min-w-0 flex-1">
                                                        <span className={`inline-flex items-center justify-center rounded-lg border px-2 py-0.5 text-xs font-black shrink-0 ${rankBadgeStyle}`}>
                                                            {rankMedals[idx] ?? `#${idx + 1}`}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <h4 className="font-bold text-slate-900 group-hover:text-red-700 transition leading-snug break-words">
                                                                {office.office_name}
                                                            </h4>
                                                            <p className="mt-0.5 text-xs text-slate-500 flex items-center gap-2">
                                                                <span>{office.purchase_order_count} POs</span>
                                                                <span className="text-slate-300">·</span>
                                                                <span>{office.purchase_request_count} PRs</span>
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div className="text-right shrink-0">
                                                        <p className="text-base font-extrabold text-slate-900">
                                                            {formatCurrency(office.purchase_order_total_amount)}
                                                        </p>
                                                        <span className="inline-flex items-center rounded-full bg-red-50 border border-red-200 px-2 py-0.5 text-[11px] font-bold text-red-700 mt-0.5">
                                                            {percent}% of spend
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                                    <div
                                                        className={`h-full rounded-full bg-gradient-to-r ${barGradient} transition-all duration-700`}
                                                        style={{ width: `${barWidth}%` }}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </ExecutiveChartCard>
                    </div>

                    {/* Requisition & Purchase Order Status Mix (xl:col-span-6) */}
                    <div className="xl:col-span-6 flex flex-col">
                        <ExecutiveChartCard
                            title="Requisition & Order Status Mix"
                            subtitle="Procurement workflow progression and lifecycle distribution"
                            tone="default"
                            className="h-full"
                        >
                            <div className="flex flex-col h-full divide-y divide-slate-100">
                                <div className="pb-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                            Purchase Request (PR) Mix
                                        </span>
                                    </div>
                                    <DonutChart
                                        data={statusMixPr}
                                        centerLabel="PR"
                                        emptyText="No PR data in current filter scope"
                                    />
                                </div>
                                <div className="pt-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                            Purchase Order (PO) Mix
                                        </span>
                                    </div>
                                    <DonutChart
                                        data={statusMixPo}
                                        centerLabel="PO"
                                        emptyText="No PO data in current filter scope"
                                    />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>
                </div>

                <section id="latest-procurement-records-table" className="scroll-mt-6 overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
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
                            {procurementRecords.links && procurementRecords.links.length > 3 && (
                                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
                                    <span className="text-xs font-medium text-slate-500">
                                        Showing {procurementRecords.data.length} records
                                    </span>
                                    <PaginationNav links={procurementRecords.links} preserveState={false} />
                                </div>
                            )}
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
                        <div className="rounded-lg border border-slate-300 bg-slate-50/50 p-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                Procurement Activity & Expenditure Totals
                            </h3>
                            <div className="grid grid-cols-3 gap-2.5">
                                <div className="rounded-md border border-slate-300 bg-white p-2.5 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Purchase Requests</p>
                                    <p className="mt-0.5 text-xl font-extrabold text-slate-900">{totals.purchase_request_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-2.5 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Purchase Orders</p>
                                    <p className="mt-0.5 text-xl font-extrabold text-slate-900">{totals.purchase_order_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-2.5 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Committed Spend</p>
                                    <p className="mt-0.5 text-lg font-extrabold text-red-800">{formatCurrency(totals.purchase_order_total_amount)}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3.5">
                            <section className="rounded-lg border border-slate-300 bg-white p-3">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2">
                                    Monthly Procurement Expenditure Trend
                                </h3>
                                <SpendTrendBarChart
                                    data={spendTrendPoints}
                                    emptyText="No data in current filter scope"
                                />
                            </section>

                            <section className="rounded-lg border border-slate-300 bg-white p-3 flex flex-col justify-between">
                                <div>
                                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2">
                                        Office Procurement Spending Concentration
                                    </h3>
                                    <div className="space-y-2">
                                        {office_comparison.slice(0, 4).map((office, idx) => {
                                            const percent = office.spending_share_percentage ?? (totalProcurementSpend > 0 ? Math.round((office.purchase_order_total_amount / totalProcurementSpend) * 100) : 0);
                                            const barWidth = Math.max((office.purchase_order_total_amount / maxOfficeSpend) * 100, office.purchase_order_total_amount > 0 ? 6 : 0);
                                            return (
                                                <div key={office.office_id} className="text-[11px] border-b border-slate-100 pb-1.5 last:border-b-0">
                                                    <table className="w-full text-[11px] table-fixed mb-1">
                                                        <tbody>
                                                            <tr className="font-semibold">
                                                                <td className="w-[62%] text-left text-slate-800 pr-2 truncate">
                                                                    #{idx + 1} {office.office_name}
                                                                </td>
                                                                <td className="w-[38%] text-right text-slate-900 whitespace-nowrap">
                                                                    {formatCurrency(office.purchase_order_total_amount)} ({percent}%)
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <div className="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden block">
                                                        <div className="h-full bg-red-600 rounded-full block" style={{ width: `${barWidth}%` }} />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                                {office_comparison.length > 4 && (
                                    <p className="mt-1 text-[10px] text-slate-400 italic text-right">
                                        Top 4 of {office_comparison.length} procuring offices shown
                                    </p>
                                )}
                            </section>
                        </div>

                        <section className="rounded-lg border border-slate-300 bg-white p-3 mt-2.5">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2">
                                Procurement Velocity & Activity Volume Trend
                            </h3>
                            <TrendLineChart
                                data={totalActivityTrend}
                                emptyText="No procurement activity volume data in current filter scope"
                                showPointCards={false}
                            />
                        </section>
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

function mergeTrend(requestTrend: TrendRow[], orderTrend: TrendRow[], isSingleYear?: boolean) {
    const monthMap = new Map<string, { pr: number; po: number }>();

    requestTrend.forEach((row) => {
        const existing = monthMap.get(row.month) ?? { pr: 0, po: 0 };
        monthMap.set(row.month, { ...existing, pr: row.total });
    });

    orderTrend.forEach((row) => {
        const existing = monthMap.get(row.month) ?? { pr: 0, po: 0 };
        monthMap.set(row.month, { ...existing, po: row.total });
    });

    const entries = [...monthMap.entries()].sort(([monthA], [monthB]) => monthA.localeCompare(monthB));
    const uniqueYears = [...new Set(entries.map(([month]) => month.split('-')[0]))];
    const singleYear = isSingleYear ?? uniqueYears.length <= 1;

    return entries.map(([month, totals]) => ({
        label: formatMonth(month, singleYear),
        first: totals.pr,
        second: totals.po,
    }));
}

function formatMonth(value: string, isSingleYear?: boolean): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    if (isSingleYear) {
        return date.toLocaleDateString('en-PH', { month: 'short' });
    }

    const monthStr = date.toLocaleDateString('en-PH', { month: 'short' });
    const yearStr = date.toLocaleDateString('en-PH', { year: '2-digit' });
    return `${monthStr} '${yearStr}`;
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
