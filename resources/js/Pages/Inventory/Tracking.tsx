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
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-900">GSO Dashboard</h2>
                    <p className="mt-1 text-sm font-normal text-slate-500">Inventory, deliveries, distribution, and claims overview.</p>
                </div>
            }
        >
            <Head title="Inventory Dashboard" />

            <div className="space-y-6 py-8">
                <DashboardAlertBanner alerts={dashboardAlerts} />

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-red-700">GSO Overview</p>
                            <h3 className="mt-1 text-2xl font-bold text-slate-950">Today&apos;s inventory snapshot</h3>
                        </div>
                        <p className="max-w-xl text-sm leading-6 text-slate-500">A cleaner summary of stock availability, deliveries, distributions, and items needing attention.</p>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard label="Available Units" value={formatQuantity(summary.total_available_stock)} tone="emerald" helper="Ready for office release" />
                        <StatCard label="Low Stock" value={summary.low_stock_count} tone="amber" helper="Items at or below threshold" />
                        <StatCard label="Pending Deliveries" value={summary.pending_delivery_count} tone="blue" helper="Awaiting receipt or verification" />
                        <StatCard label="Pending Claims" value={summary.open_claim_count} tone="rose" helper="Claims needing GSO review" />
                    </div>

                    <div className="mt-5 grid gap-3 border-t border-slate-100 pt-5 sm:grid-cols-2 xl:grid-cols-4">
                        <MiniKpi label="Inventory Records" value={summary.inventory_count} />
                        <MiniKpi label="Movement Logs" value={summary.movement_count} />
                        <MiniKpi label="Released" value={summary.released_distribution_count} />
                        <MiniKpi label="Received" value={summary.received_distribution_count} />
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 className="text-lg font-semibold text-slate-950">Quick Actions</h3>
                            <p className="mt-1 text-sm text-slate-500">Most-used GSO tasks in one clean area.</p>
                        </div>
                    </div>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {quickActions.map((action) => (
                            <ActionLink key={action.href} href={action.href} label={action.label} description={action.description} />
                        ))}
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Delivery and Distribution">
                        <p className="text-sm text-slate-600">Track delivery receipts, item verification, and office release records from one flow.</p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Link href={route('inventory.deliveries.index')} className="rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white hover:bg-red-800">
                                View Deliveries
                            </Link>
                            <Link href={route('inventory.distribution-records.index')} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                View Distribution
                            </Link>
                        </div>
                    </Panel>

                    <Panel title="Stock and Claims">
                        <p className="text-sm text-slate-600">Review available stock, low-stock pressure, and unresolved claims requiring action.</p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Link href={route('inventory.inventory.index')} className="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                View Stock
                            </Link>
                            <Link href={route('inventory.claims.index')} className="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">
                                View Claims
                            </Link>
                            <Link href={route('inventory.inventory-movements.index')} className="rounded-lg bg-slate-600 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                                Movement Logs
                            </Link>
                        </div>
                    </Panel>
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





