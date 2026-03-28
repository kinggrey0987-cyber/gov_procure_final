import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Supplier = {
    supplier_id: number;
    supplier_name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: string;
};

type Props = {
    supplier: Supplier;
};

export default function SuppliersEdit({ supplier }: Props) {
    const form = useForm({
        supplier_name: supplier.supplier_name,
        contact_person: supplier.contact_person ?? '',
        email: supplier.email ?? '',
        phone: supplier.phone ?? '',
        address: supplier.address ?? '',
        status: supplier.status,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('procurement.suppliers.update', supplier.supplier_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Supplier</h2>}>
            <Head title="Edit Supplier" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-6">
                        <input
                            type="text"
                            value={form.data.supplier_name}
                            onChange={(event) => form.setData('supplier_name', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            value={form.data.contact_person}
                            onChange={(event) => form.setData('contact_person', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <textarea
                            value={form.data.address}
                            onChange={(event) => form.setData('address', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            rows={3}
                        />
                        <select
                            value={form.data.status}
                            onChange={(event) => form.setData('status', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        >
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>

                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Update Supplier
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
