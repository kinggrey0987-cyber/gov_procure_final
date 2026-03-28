import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type SupplierRow = {
    supplier_id: number;
    supplier_name: string;
    status: string;
};

type Props = {
    suppliers: {
        data: SupplierRow[];
    };
};

export default function SuppliersIndex({ suppliers }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Suppliers</h2>}>
            <Head title="Suppliers" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('procurement.suppliers.create')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Create Supplier
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">Supplier</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {suppliers.data.map((row) => (
                                    <tr key={row.supplier_id} className="border-t">
                                        <td className="px-3 py-2">{row.supplier_name}</td>
                                        <td className="px-3 py-2">{row.status}</td>
                                        <td className="px-3 py-2">
                                            <Link href={route('procurement.suppliers.show', row.supplier_id)} className="text-indigo-600 hover:underline">
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
