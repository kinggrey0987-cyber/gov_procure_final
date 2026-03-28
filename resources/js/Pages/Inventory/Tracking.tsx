import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    summary: {
        inventory_count: number;
        low_stock_count: number;
        movement_count: number;
        released_distribution_count: number;
        received_distribution_count: number;
        open_claim_count: number;
    };
};

export default function InventoryTracking({ summary }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard Overview</h2>}>
            <Head title="Inventory Dashboard" />

            <div className="space-y-6 py-8">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard label="Inventory Records" value={summary.inventory_count} tone="slate" />
                    <StatCard label="Low Stock" value={summary.low_stock_count} tone="amber" />
                    <StatCard label="Movement Logs" value={summary.movement_count} tone="sky" />
                    <StatCard label="Released Distribution" value={summary.released_distribution_count} tone="blue" />
                    <StatCard label="Received Distribution" value={summary.received_distribution_count} tone="emerald" />
                    <StatCard label="Open Claims" value={summary.open_claim_count} tone="rose" />
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-base font-semibold text-slate-900">Quick Actions</h3>
                    <p className="mt-1 text-sm text-slate-500">Direct access to active GSO operations.</p>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <ActionLink href={route('inventory.deliveries.index')} label="Delivery Monitoring" />
                        <ActionLink href={route('inventory.deliveries.create')} label="Receive Deliveries" />
                        <ActionLink href={route('inventory.distribution-records.index')} label="Distribution Records" />
                        <ActionLink href={route('inventory.distribution-records.create')} label="Supply Distribution" />
                        <ActionLink href={route('inventory.claims.index')} label="Office Supply Claims" />
                        <ActionLink href={route('inventory.inventory.index')} label="Inventory Monitoring" />
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Delivery and Distribution">
                        <p className="text-sm text-slate-600">Track delivery receipts, item verification, and office release records from one flow.</p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Link href={route('inventory.deliveries.index')} className="rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white hover:bg-red-800">
                                Open Deliveries
                            </Link>
                            <Link href={route('inventory.distribution-records.index')} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                Open Distribution
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

function StatCard({ label, value, tone }: { label: string; value: number; tone: 'slate' | 'amber' | 'sky' | 'blue' | 'emerald' | 'rose' }) {
    const classes = {
        slate: 'text-slate-700 bg-slate-50',
        amber: 'text-amber-700 bg-amber-50',
        sky: 'text-sky-700 bg-sky-50',
        blue: 'text-blue-700 bg-blue-50',
        emerald: 'text-emerald-700 bg-emerald-50',
        rose: 'text-rose-700 bg-rose-50',
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-sm text-slate-500">{label}</p>
            <p className={`mt-2 inline-flex rounded-md px-3 py-1 text-2xl font-semibold ${classes[tone]}`}>{value}</p>
        </div>
    );
}

function ActionLink({ href, label }: { href: string; label: string }) {
    return (
        <Link href={href} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">
            {label}
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
