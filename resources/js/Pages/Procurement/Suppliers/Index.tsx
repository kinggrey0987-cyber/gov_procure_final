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
        <AuthenticatedLayout
            header="Supplier Management"
            headerActions={
                <Link
                    href={route('procurement.suppliers.create')}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition"
                >
                    <span className="text-sm leading-none">＋</span>
                    <span>Add Supplier</span>
                </Link>
            }
        >
            <Head title="Supplier Management" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white px-3.5 py-2 shadow-sm flex items-center justify-between">
                    <p className="text-xs text-slate-600">Maintain active supplier records and vendor compliance used across Procurement Unit processing.</p>
                    <span className="text-[11px] text-slate-500 font-medium">Showing {suppliers.data.length} records</span>
                </section>

                <section className="flex-1 min-h-0 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                            <tr>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Supplier Name</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Contact Info</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {suppliers.data.map((row) => (
                                <tr key={row.supplier_id} className="hover:bg-slate-50/60 transition-colors">
                                    <td className="px-3.5 py-2 text-slate-800">
                                        <p className="font-semibold text-slate-900">{row.supplier_name}</p>
                                        <p className="text-[11px] text-slate-500">POs: {row.purchase_orders_count ?? 0}</p>
                                    </td>
                                    <td className="px-3.5 py-2 text-slate-700">
                                        <p className="font-medium">{row.contact_person || '-'}</p>
                                        <p className="text-[11px] text-slate-500">{row.email || row.phone || '-'}</p>
                                    </td>
                                    <td className="px-3.5 py-2 text-slate-700">{row.primary_category || 'Uncategorized'}</td>
                                    <td className="px-3.5 py-2">
                                        <span className={statusClass(row.status)}>{row.status}</span>
                                    </td>
                                    <td className="px-3.5 py-2 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Link href={route('procurement.suppliers.show', row.supplier_id)} className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition">
                                                View
                                            </Link>
                                            <Link href={route('procurement.suppliers.edit', row.supplier_id)} className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                                                Edit
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {suppliers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No suppliers available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <div className="shrink-0 pt-0.5">
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





