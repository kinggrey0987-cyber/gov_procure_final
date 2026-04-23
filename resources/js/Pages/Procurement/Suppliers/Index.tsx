import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, Link } from '@inertiajs/react';

type SupplierRow = {
    supplier_id: number;
    supplier_name: string;
    status: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    primary_category?: string | null;
    purchase_orders_count?: number;
};

type Props = {
    suppliers: {
        data: SupplierRow[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
};

export default function SuppliersIndex({ suppliers }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supplier Management</h2>}>
            <Head title="Supplier Management" />

            <div className="space-y-4 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">Maintain active supplier records used by BAC procurement processing.</p>
                    <Link href={route('procurement.suppliers.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Add Supplier
                    </Link>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Supplier Name</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Contact Info</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {suppliers.data.map((row) => (
                                <tr key={row.supplier_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 text-slate-800">
                                        <p className="font-semibold">{row.supplier_name}</p>
                                        <p className="text-xs text-slate-500">POs: {row.purchase_orders_count ?? 0}</p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">
                                        <p>{row.contact_person || '-'}</p>
                                        <p className="text-xs text-slate-500">{row.email || row.phone || '-'}</p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{row.primary_category || 'Uncategorized'}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.status)}>{row.status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link href={route('procurement.suppliers.show', row.supplier_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                                View
                                            </Link>
                                            <Link href={route('procurement.suppliers.edit', row.supplier_id)} className="text-sm font-semibold text-slate-700 hover:text-slate-900">
                                                Edit
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {suppliers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No suppliers available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <div>
                    <PaginationNav links={suppliers.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        ACTIVE: `${base} bg-emerald-100 text-emerald-700`,
        INACTIVE: `${base} bg-slate-200 text-slate-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}
