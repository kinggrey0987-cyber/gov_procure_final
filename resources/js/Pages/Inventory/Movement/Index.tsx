import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type MovementRow = {
    movement_id: number;
    movement_type: string;
    inventory_id: number | string;
    quantity: string;
    movement_date: string;
};

type Props = {
    movements: {
        data: MovementRow[];
    };
};

export default function MovementIndex({ movements }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Movement Logs</h2>}>
            <Head title="Inventory Movements" />
            <div className="py-8">
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Inventory</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Quantity</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {movements.data.map((row) => (
                                <tr key={row.movement_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 text-slate-700">{row.movement_type}</td>
                                    <td className="px-4 py-3 text-slate-700">#{row.inventory_id}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-slate-700">{row.quantity}</td>
                                    <td className="px-4 py-3 text-slate-600">{row.movement_date}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('inventory.inventory-movements.show', row.movement_id)}
                                            className="text-sm font-semibold text-blue-600 hover:text-blue-800"
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
        </AuthenticatedLayout>
    );
}
