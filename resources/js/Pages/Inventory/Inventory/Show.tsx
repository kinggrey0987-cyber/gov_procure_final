import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Link } from '@inertiajs/react';
import { Head } from '@inertiajs/react';

type Props = {
    inventory: {
        inventory_id: number;
        item_id: number;
        available_stock: string;
        stock_on_hand: string;
        reserved_quantity: string;
        released_quantity: string;
        low_stock_threshold: string;
        item?: {
            item_name?: string;
            category?: {
                category_name?: string;
            };
        };
    };
    recentMovements: Array<{
        movement_id: number;
        movement_type: string;
        quantity: string;
        movement_date: string;
    }>;
};

export default function InventoryShow({ inventory, recentMovements }: Props) {
    const isLowStock = Number(inventory.available_stock) < Number(inventory.low_stock_threshold);

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Detail</h2>
                    <Link href={route('inventory.inventory.index')} className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to Inventory Monitoring
                    </Link>
                </div>
            }
        >
            <Head title="Inventory Detail" />

            <div className="space-y-6 py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900">{inventory.item?.item_name ?? `Item #${inventory.item_id}`}</h3>
                    <p className="mt-1 text-sm text-slate-500">Category: {inventory.item?.category?.category_name ?? 'Uncategorized'} • Inventory ID: {inventory.inventory_id}</p>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Metric label="Available" value={inventory.available_stock} tone={isLowStock ? 'warning' : 'default'} />
                        <Metric label="On Hand" value={inventory.stock_on_hand} />
                        <Metric label="Reserved" value={inventory.reserved_quantity} />
                        <Metric label="Released" value={inventory.released_quantity} />
                        <Metric label="Low Stock Threshold" value={inventory.low_stock_threshold} />
                    </div>

                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p>
                            <span className="font-semibold text-slate-800">Threshold meaning:</span> Low Stock Threshold is the safety level used to signal reorder risk.
                            Values below this level appear as active low-stock shortage signals. Values equal to threshold are advisory reorder-point warnings.
                        </p>
                    </div>
                </div>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Recent Movement Activity</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-2 text-left font-semibold text-slate-700">Movement Type</th>
                                <th className="px-4 py-2 text-right font-semibold text-slate-700">Quantity</th>
                                <th className="px-4 py-2 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-2 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {recentMovements.map((movement) => (
                                <tr key={movement.movement_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-2 text-slate-700">{humanizeEnum(movement.movement_type)}</td>
                                    <td className="px-4 py-2 text-right text-slate-700">{movement.quantity}</td>
                                    <td className="px-4 py-2 text-slate-600">{movement.movement_date}</td>
                                    <td className="px-4 py-2 text-right">
                                        <Link href={route('inventory.inventory-movements.show', movement.movement_id)} className="text-sm font-semibold text-blue-600 hover:text-blue-800">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {recentMovements.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-6 text-center text-sm text-slate-500">
                                        No movement activity recorded yet for this item.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Metric({ label, value, tone = 'default' }: { label: string; value: string; tone?: 'default' | 'warning' }) {
    return (
        <div className={`rounded-lg border px-3 py-2 ${tone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-800'}`}>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-lg font-semibold">{value}</p>
        </div>
    );
}
