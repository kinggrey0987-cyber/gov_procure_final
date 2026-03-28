import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function SuppliersCreate() {
    const form = useForm({
        supplier_name: '',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        status: 'ACTIVE',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('procurement.suppliers.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Supplier</h2>}>
            <Head title="Create Supplier" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-6">
                        <input
                            type="text"
                            placeholder="Supplier Name"
                            value={form.data.supplier_name}
                            onChange={(event) => form.setData('supplier_name', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Contact Person"
                            value={form.data.contact_person}
                            onChange={(event) => form.setData('contact_person', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="email"
                            placeholder="Email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Phone"
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <textarea
                            placeholder="Address"
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
                            Save Supplier
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
