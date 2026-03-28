import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type MovementRow = {
    movement_id: number;
    movement_type: string;
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Movements</h2>}>
            <Head title="Inventory Movements" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Type</th>
                                    <th className="px-4 py-2 text-left">Quantity</th>
                                    <th className="px-4 py-2 text-left">Date</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {movements.data.map((row) => (
                                    <tr key={row.movement_id}>
                                        <td className="px-4 py-2">{row.movement_type}</td>
                                        <td className="px-4 py-2">{row.quantity}</td>
                                        <td className="px-4 py-2">{row.movement_date}</td>
                                        <td className="px-4 py-2">
                                            <Link
                                                href={route('inventory.inventory-movements.show', row.movement_id)}
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
