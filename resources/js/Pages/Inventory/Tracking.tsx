import DashboardAlertBanner, { type DashboardAlert } from '@/Components/DashboardAlertBanner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    summary: {
        inventory_count: number;
        low_stock_count: number;
        out_of_stock_count: number;
        total_available_stock: number;
        reserved_quantity: number;
        movement_count: number;
        released_distribution_count: number;
        received_distribution_count: number;
        pending_delivery_count: number;
        open_claim_count: number;
    };
    dashboardAlerts: DashboardAlert[];
};

export default function InventoryTracking({ summary, dashboardAlerts }: Props) {
    return (
        <AuthenticatedLayout header="GSO Dashboard">
            <Head title="GSO Dashboard" />

            <div className="space-y-2.5 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                <DashboardAlertBanner alerts={dashboardAlerts} />

                {/* Unified High-Density GSO Telemetry Cockpit - Interactive Drilldown */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs space-y-1.5">
                    {/* Stock & Operational Pressure Tier */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <Link
                            href={route('inventory.inventory.index')}
                            className="flex items-center gap-2.5 px-3 py-1.5 transition rounded-lg group cursor-pointer hover:bg-emerald-50/60"
                            title="Click to view Inventory Stock Ledger"
                        >
                            <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-600 ring-2 ring-emerald-100" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 group-hover:text-emerald-800">Available Units</p>
                                <p className="text-xl font-extrabold text-emerald-900 leading-tight">{formatQuantity(summary.total_available_stock)}</p>
                            </div>
                        </Link>
                        <Link
                            href={route('inventory.inventory.index')}
                            className="flex items-center gap-2.5 px-3 py-1.5 transition rounded-lg group cursor-pointer hover:bg-amber-50/60"
                            title="Click to view Low Stock items"
                        >
                            <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500 ring-2 ring-amber-100" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-amber-700 group-hover:text-amber-800">Low Stock</p>
                                <p className="text-xl font-extrabold text-amber-900 leading-tight">{summary.low_stock_count}</p>
                            </div>
                        </Link>
                        <Link
                            href={route('inventory.deliveries.index')}
                            className="flex items-center gap-2.5 px-3 py-1.5 transition rounded-lg group cursor-pointer hover:bg-blue-50/60"
                            title="Click to view Deliveries queue"
                        >
                            <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-blue-600 ring-2 ring-blue-100" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-blue-700 group-hover:text-blue-800">Pending Deliveries</p>
                                <p className="text-xl font-extrabold text-blue-900 leading-tight">{summary.pending_delivery_count}</p>
                            </div>
                        </Link>
                        <Link
                            href={route('inventory.claims.index')}
                            className="flex items-center gap-2.5 px-3 py-1.5 transition rounded-lg group cursor-pointer hover:bg-rose-50/60"
                            title="Click to review Pending Claims"
                        >
                            <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-rose-600 ring-2 ring-rose-100" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-rose-700 group-hover:text-rose-800">Pending Claims</p>
                                <p className="text-xl font-extrabold text-rose-900 leading-tight">{summary.open_claim_count}</p>
                            </div>
                        </Link>
                    </div>

                    {/* Ledger Volume Tier */}
                    <div className="border-t border-slate-100 pt-1.5 grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <Link
                            href={route('inventory.items.index')}
                            className="flex items-center gap-2.5 px-3 py-0.5 transition rounded-lg group cursor-pointer hover:bg-slate-50"
                            title="Click to view Item Catalog"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Inventory Items</p>
                                <p className="text-base font-extrabold text-slate-900 leading-tight">{summary.inventory_count}</p>
                            </div>
                        </Link>
                        <Link
                            href={route('inventory.inventory-movements.index')}
                            className="flex items-center gap-2.5 px-3 py-0.5 transition rounded-lg group cursor-pointer hover:bg-indigo-50/60"
                            title="Click to view Movement Logs"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-indigo-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-indigo-700 group-hover:text-indigo-800">Movement Logs</p>
                                <p className="text-base font-extrabold text-indigo-900 leading-tight">{summary.movement_count}</p>
                            </div>
                        </Link>
                        <Link
                            href={route('inventory.distribution-records.index')}
                            className="flex items-center gap-2.5 px-3 py-0.5 transition rounded-lg group cursor-pointer hover:bg-emerald-50/60"
                            title="Click to view Released Records"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 group-hover:text-emerald-800">Released Records</p>
                                <p className="text-base font-extrabold text-emerald-900 leading-tight">{summary.released_distribution_count}</p>
                            </div>
                        </Link>
                        <Link
                            href={route('inventory.distribution-records.index')}
                            className="flex items-center gap-2.5 px-3 py-0.5 transition rounded-lg group cursor-pointer hover:bg-teal-50/60"
                            title="Click to view Received Records"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-teal-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-teal-700 group-hover:text-teal-800">Received Records</p>
                                <p className="text-base font-extrabold text-teal-900 leading-tight">{summary.received_distribution_count}</p>
                            </div>
                        </Link>
                    </div>
                </section>

                {/* Primary Dual Operational Command Hubs */}
                <div className="grid gap-2.5 lg:grid-cols-2 flex-1 min-h-0">
                    {/* Delivery & Distribution Hub */}
                    <div className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <div>
                                    <h3 className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                        <svg className="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                        </svg>
                                        Delivery & Distribution
                                    </h3>
                                    <p className="text-[10px] text-slate-500">Warehouse intake receipts and office supply releases</p>
                                </div>
                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${summary.pending_delivery_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                                    {summary.pending_delivery_count > 0 ? `${summary.pending_delivery_count} Pending Deliveries` : 'Intake Up to Date'}
                                </span>
                            </div>

                            {/* Direct Action Buttons - Always Visible Without Scrolling */}
                            <div className="mt-2.5 flex flex-wrap items-center gap-2">
                                <Link
                                    href={route('inventory.deliveries.index')}
                                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 transition shadow-xs"
                                >
                                    <span>Monitor Deliveries</span>
                                    {summary.pending_delivery_count > 0 ? (
                                        <span className="rounded-full bg-white/25 px-1.5 py-0.2 text-[10px] font-bold">{summary.pending_delivery_count}</span>
                                    ) : null}
                                    <span>&rarr;</span>
                                </Link>
                                <Link
                                    href={route('inventory.distribution-records.create')}
                                    className="inline-flex h-8 items-center gap-1 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700 transition shadow-xs"
                                >
                                    <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                                    </svg>
                                    Release Supplies
                                </Link>
                                <Link
                                    href={route('inventory.distribution-records.index')}
                                    className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                >
                                    Distribution Records &rarr;
                                </Link>
                            </div>
                        </div>

                        {/* Operational Telemetry Sub-Strip (Fills space informatively with zero blank gap) */}
                        <div className="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-slate-50/80 p-2 border border-slate-100">
                            <Link href={route('inventory.deliveries.index')} className="group text-center">
                                <p className="text-[10px] font-medium text-slate-500 group-hover:text-slate-700">Pending Deliveries</p>
                                <p className="text-sm font-bold text-slate-800 group-hover:text-red-700">{summary.pending_delivery_count}</p>
                            </Link>
                            <Link href={route('inventory.distribution-records.index')} className="group text-center border-x border-slate-200">
                                <p className="text-[10px] font-medium text-slate-500 group-hover:text-slate-700">Released Batches</p>
                                <p className="text-sm font-bold text-slate-800 group-hover:text-emerald-700">{summary.released_distribution_count}</p>
                            </Link>
                            <Link href={route('inventory.distribution-records.index')} className="group text-center">
                                <p className="text-[10px] font-medium text-slate-500 group-hover:text-slate-700">Received Batches</p>
                                <p className="text-sm font-bold text-slate-800 group-hover:text-teal-700">{summary.received_distribution_count}</p>
                            </Link>
                        </div>
                    </div>

                    {/* Stock & Claims Operations Hub */}
                    <div className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <div>
                                    <h3 className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                        <svg className="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        Stock & Claims Operations
                                    </h3>
                                    <p className="text-[10px] text-slate-500">Live bin cards, safety stock levels, and discrepancy claims</p>
                                </div>
                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${summary.low_stock_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}>
                                    {summary.low_stock_count > 0 ? `${summary.low_stock_count} Low Stock Items` : 'Stock Healthy'}
                                </span>
                            </div>

                            {/* Direct Action Buttons - Always Visible Without Scrolling */}
                            <div className="mt-2.5 flex flex-wrap items-center gap-2">
                                <Link
                                    href={route('inventory.inventory.index')}
                                    className="inline-flex h-8 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800 transition shadow-xs"
                                >
                                    Stock Ledger &rarr;
                                </Link>
                                <Link
                                    href={route('inventory.claims.index')}
                                    className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 text-xs font-semibold text-rose-800 hover:bg-rose-100 transition"
                                >
                                    <span>Review Claims</span>
                                    {summary.open_claim_count > 0 ? (
                                        <span className="rounded-full bg-rose-200 px-1.5 py-0.2 text-[10px] font-bold text-rose-900">{summary.open_claim_count}</span>
                                    ) : null}
                                    <span>&rarr;</span>
                                </Link>
                                <Link
                                    href={route('inventory.inventory-movements.index')}
                                    className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                >
                                    Movement Logs &rarr;
                                </Link>
                            </div>
                        </div>

                        {/* Operational Telemetry Sub-Strip (Fills space informatively with zero blank gap) */}
                        <div className="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-slate-50/80 p-2 border border-slate-100">
                            <Link href={route('inventory.inventory.index')} className="group text-center">
                                <p className="text-[10px] font-medium text-slate-500 group-hover:text-slate-700">Available Stock</p>
                                <p className="text-sm font-bold text-slate-800 group-hover:text-emerald-700">{formatQuantity(summary.total_available_stock)}</p>
                            </Link>
                            <Link href={route('inventory.inventory.index')} className="group text-center border-x border-slate-200">
                                <p className="text-[10px] font-medium text-slate-500 group-hover:text-slate-700">Low Stock Alert</p>
                                <p className={`text-sm font-bold ${summary.low_stock_count > 0 ? 'text-amber-600' : 'text-slate-800'} group-hover:text-amber-700`}>{summary.low_stock_count}</p>
                            </Link>
                            <Link href={route('inventory.claims.index')} className="group text-center">
                                <p className="text-[10px] font-medium text-slate-500 group-hover:text-slate-700">Open Claims</p>
                                <p className={`text-sm font-bold ${summary.open_claim_count > 0 ? 'text-rose-600' : 'text-slate-800'} group-hover:text-rose-700`}>{summary.open_claim_count}</p>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Quick Catalog & Resource Directory Navigation */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-xs flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Catalog & Setup:</span>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Link
                                href={route('inventory.items.index')}
                                className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 transition"
                            >
                                Item Catalog ({summary.inventory_count})
                            </Link>
                            <Link
                                href={route('inventory.item-categories.index')}
                                className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 transition"
                            >
                                Item Categories
                            </Link>
                            <Link
                                href={route('inventory.item-units.index')}
                                className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 transition"
                            >
                                Item Units
                            </Link>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('inventory.office-requests.index')}
                            className="text-xs font-medium text-red-600 hover:text-red-700 transition flex items-center gap-1"
                        >
                            <span>Office Supply Requests</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatQuantity(value: number) {
    return new Intl.NumberFormat('en-PH', {
        maximumFractionDigits: 2,
    }).format(value);
}





