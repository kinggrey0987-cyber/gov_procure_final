import DashboardAlertBanner, { type DashboardAlert } from '@/Components/DashboardAlertBanner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

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
    const quickActions = [
        { href: route('inventory.deliveries.index'), label: 'Monitor Deliveries', description: 'Review incoming supplies and delivery status.' },
        { href: route('inventory.distribution-records.create'), label: 'Release Supplies', description: 'Create a distribution record for office requests.' },
        { href: route('inventory.claims.index'), label: 'Review Claims', description: 'Validate pending office supply claims.' },
        { href: route('inventory.inventory.index'), label: 'View Inventory', description: 'Check stock balances and low-stock items.' },
    ];

    return (
        <AuthenticatedLayout
            header="GSO Dashboard"
            headerActions={
                <Link
                    href={route('inventory.distribution-records.create')}
                    className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                >
                    <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                    </svg>
                    Release Supplies
                </Link>
            }
        >
            <Head title="GSO Dashboard" />

            <div className="space-y-3.5">
                <DashboardAlertBanner alerts={dashboardAlerts} />

                {/* Unified High-Density GSO Telemetry Cockpit */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm space-y-2">
                    {/* Stock & Operational Pressure Tier */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Available Units</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{formatQuantity(summary.total_available_stock)}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Low Stock</p>
                                <p className="text-lg font-extrabold text-amber-900 leading-tight">{summary.low_stock_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-blue-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-blue-700">Pending Deliveries</p>
                                <p className="text-lg font-extrabold text-blue-900 leading-tight">{summary.pending_delivery_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-rose-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-700">Pending Claims</p>
                                <p className="text-lg font-extrabold text-rose-900 leading-tight">{summary.open_claim_count}</p>
                            </div>
                        </div>
                    </div>

                    {/* Ledger Volume Tier */}
                    <div className="border-t border-slate-100 pt-2 grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Inventory Items</p>
                                <p className="text-base font-extrabold text-slate-900 leading-tight">{summary.inventory_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-indigo-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Movement Logs</p>
                                <p className="text-base font-extrabold text-indigo-900 leading-tight">{summary.movement_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Released Records</p>
                                <p className="text-base font-extrabold text-emerald-900 leading-tight">{summary.released_distribution_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-teal-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-teal-700">Received Records</p>
                                <p className="text-base font-extrabold text-teal-900 leading-tight">{summary.received_distribution_count}</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Compact Quick Access Cards */}
                <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                    {quickActions.map((action) => (
                        <Link
                            key={action.href}
                            href={action.href}
                            className="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-red-300 hover:bg-slate-50/70 hover:shadow"
                        >
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700 transition group-hover:bg-red-600 group-hover:text-white">
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </span>
                            <div className="min-w-0">
                                <p className="text-xs font-bold text-slate-900 group-hover:text-red-700">{action.label}</p>
                                <p className="text-[11px] text-slate-500 truncate">{action.description}</p>
                            </div>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-3.5 lg:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Delivery & Distribution</h3>
                                <p className="text-[11px] text-slate-500">Track delivery receipts and office release records</p>
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Link href={route('inventory.deliveries.index')} className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 transition shadow-sm">
                                View Deliveries &rarr;
                            </Link>
                            <Link href={route('inventory.distribution-records.index')} className="inline-flex h-8 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 transition">
                                Distribution Records &rarr;
                            </Link>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Stock & Claims Operations</h3>
                                <p className="text-[11px] text-slate-500">Available stock, reorder levels, and pending claims</p>
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Link href={route('inventory.inventory.index')} className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                                Stock Ledger &rarr;
                            </Link>
                            <Link href={route('inventory.claims.index')} className="inline-flex h-8 items-center rounded-lg border border-rose-300 bg-rose-50 px-3 text-xs font-semibold text-rose-800 hover:bg-rose-100 transition">
                                Review Claims &rarr;
                            </Link>
                            <Link href={route('inventory.inventory-movements.index')} className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                                Movement Logs &rarr;
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value, tone, helper }: { label: string; value: number | string; tone: 'slate' | 'amber' | 'sky' | 'blue' | 'emerald' | 'rose'; helper?: string }) {
    const classes = {
        slate: 'text-slate-700 bg-slate-50',
        amber: 'text-amber-700 bg-amber-50',
        sky: 'text-sky-700 bg-sky-50',
        blue: 'text-blue-700 bg-blue-50',
        emerald: 'text-emerald-700 bg-emerald-50',
        rose: 'text-rose-700 bg-rose-50',
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className={`mt-3 inline-flex rounded-lg px-3 py-1 text-2xl font-bold ${classes[tone]}`}>{value}</p>
            {helper ? <p className="mt-2 text-xs leading-5 text-slate-500">{helper}</p> : null}
        </div>
    );
}

function MiniKpi({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-xl font-bold text-slate-900">{value}</p>
        </div>
    );
}

function formatQuantity(value: number) {
    return new Intl.NumberFormat('en-PH', {
        maximumFractionDigits: 2,
    }).format(value);
}

function ActionLink({ href, label, description }: { href: string; label: string; description: string }) {
    return (
        <Link href={href} className="group rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-red-200 hover:bg-red-50">
            <span className="text-sm font-semibold text-slate-900 group-hover:text-red-700">{label}</span>
            <span className="mt-2 block text-xs leading-5 text-slate-500">{description}</span>
        </Link>
    );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="text-base font-semibold text-slate-900">{title}</h3>
            <div className="mt-3">{children}</div>
        </section>
    );
}





