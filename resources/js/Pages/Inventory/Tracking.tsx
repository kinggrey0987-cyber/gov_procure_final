import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Tracking</h2>}>
            <Head title="Inventory Tracking" />

            <div className="py-10">
                <div className="mx-auto grid max-w-6xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-3 sm:px-6 lg:px-8">
                    <StatCard label="Inventory Records" value={summary.inventory_count} />
                    <StatCard label="Low Stock" value={summary.low_stock_count} />
                    <StatCard label="Movement Logs" value={summary.movement_count} />
                    <StatCard label="Released Distribution" value={summary.released_distribution_count} />
                    <StatCard label="Received Distribution" value={summary.received_distribution_count} />
                    <StatCard label="Open Claims" value={summary.open_claim_count} />
                </div>

                <div className="mx-auto mt-6 flex max-w-6xl flex-wrap gap-3 px-4 sm:px-6 lg:px-8">
                    <Link href={route('inventory.inventory.index')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                        Inventory
                    </Link>
                    <Link
                        href={route('inventory.distribution-records.index')}
                        className="rounded bg-emerald-600 px-4 py-2 text-sm text-white"
                    >
                        Distribution
                    </Link>
                    <Link href={route('inventory.claims.index')} className="rounded bg-sky-600 px-4 py-2 text-sm text-white">
                        Claims
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border bg-white p-4">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}
