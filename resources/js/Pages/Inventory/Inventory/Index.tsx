import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type InventoryRow = {
    inventory_id: number;
    item_id: number;
    available_stock: string;
    stock_on_hand: string;
    released_quantity: string;
    low_stock_threshold: string;
};

type Props = {
    inventories: {
        data: InventoryRow[];
    };
};

export default function InventoryIndex({ inventories }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory</h2>}>
            <Head title="Inventory" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Item ID</th>
                                    <th className="px-4 py-2 text-left">Available</th>
                                    <th className="px-4 py-2 text-left">On Hand</th>
                                    <th className="px-4 py-2 text-left">Released</th>
                                    <th className="px-4 py-2 text-left">Low Stock Threshold</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {inventories.data.map((row) => (
                                    <tr key={row.inventory_id}>
                                        <td className="px-4 py-2">{row.item_id}</td>
                                        <td className="px-4 py-2">{row.available_stock}</td>
                                        <td className="px-4 py-2">{row.stock_on_hand}</td>
                                        <td className="px-4 py-2">{row.released_quantity}</td>
                                        <td className="px-4 py-2">{row.low_stock_threshold}</td>
                                        <td className="px-4 py-2">
                                            <Link
                                                href={route('inventory.inventory.show', row.inventory_id)}
                                                className="rounded bg-indigo-600 px-3 py-1 text-xs text-white"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
