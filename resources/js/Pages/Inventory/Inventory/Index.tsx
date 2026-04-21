import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type InventoryRow = {
    inventory_id: number;
    item_id: number;
    reserved_quantity: string;
    available_stock: string;
    stock_on_hand: string;
    released_quantity: string;
    low_stock_threshold: string;
    item?: {
        item_name?: string;
        category?: {
            category_name?: string;
        };
    };
};

type Props = {
    inventories: {
        data: InventoryRow[];
    };
};

export default function InventoryIndex({ inventories }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Monitoring</h2>
                    <Link href={route('inventory.inventory.index')} className="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                        Refresh
                    </Link>
                </div>
            }
        >
            <Head title="Inventory" />

            <div className="py-8">
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Available</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">On Hand</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Reserved</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Released</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Low Stock Threshold</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Stock Signal</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {inventories.data.map((row) => {
                                const isLow = Number(row.available_stock) <= Number(row.low_stock_threshold);
                                return (
                                    <tr key={row.inventory_id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3 text-slate-700">
                                            <p>{row.item?.item_name ?? `Item #${row.item_id}`}</p>
                                            <p className="text-xs text-slate-500">ID: {row.item_id}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{row.item?.category?.category_name ?? '-'}</td>
                                        <td className={`px-4 py-3 text-right font-semibold ${isLow ? 'text-amber-700' : 'text-slate-700'}`}>{row.available_stock}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.stock_on_hand}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.reserved_quantity}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.released_quantity}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.low_stock_threshold}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${isLow ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                                {isLow ? 'Low Stock' : 'Healthy Stock'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={route('inventory.inventory.show', row.inventory_id)} className="text-sm font-semibold text-blue-600 hover:text-blue-800">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                            {inventories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No inventory records available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
