import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Supplier = {
    supplier_id: number;
    supplier_name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: string;
    categories?: Array<{
        item_category_id: number;
        category_name: string;
    }>;
};

type Props = {
    supplier: Supplier;
    permissions?: {
        update?: boolean;
    };
};

export default function SuppliersShow({ supplier, permissions }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supplier</h2>}>
            <Head title="Supplier" />

            <div className="py-10">
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-[0.15em] text-slate-500">Supplier Profile</p>
                                <h3 className="mt-1 text-xl font-semibold text-slate-900">{supplier.supplier_name}</h3>
                            </div>
                            <span className={statusClass(supplier.status)}>{supplier.status}</span>
                        </div>

                        <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                            <Info label="Contact Person" value={supplier.contact_person ?? '-'} />
                            <Info label="Email" value={supplier.email ?? '-'} />
                            <Info label="Phone" value={supplier.phone ?? '-'} />
                            <Info label="Address" value={supplier.address ?? '-'} />
                        </dl>

                        <div className="mt-4 pt-4 border-t border-slate-100">
                            <dt className="text-xs uppercase tracking-wide text-slate-500 mb-2">Accredited Commodity Categories</dt>
                            {supplier.categories && supplier.categories.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {supplier.categories.map((cat) => (
                                        <span
                                            key={cat.item_category_id}
                                            className="inline-flex items-center rounded-lg border border-red-200 bg-red-50/60 px-3 py-1 text-xs font-semibold text-red-800"
                                        >
                                            {cat.category_name}
                                        </span>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-slate-500 italic">No specific commodity categories accredited yet. This vendor can be selected for any unconstrained purchases.</p>
                            )}
                        </div>
                    </section>

                    <div className="flex flex-wrap gap-3">
                        {permissions?.update ? (
                            <Link href={route('procurement.suppliers.edit', supplier.supplier_id)} className="rounded bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                Edit Supplier
                            </Link>
                        ) : null}
                        <Link href={route('procurement.suppliers.index')} className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Back to Suppliers
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 font-medium text-slate-800">{value}</dd>
        </div>
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




