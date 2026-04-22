import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
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
                <div className="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Supplier Registry</p>
                        <h3 className="mt-1 text-lg font-semibold text-slate-900">Create Supplier Profile</h3>
                        <p className="mt-1 text-sm text-slate-600">Capture supplier contact details used for canvass, quotation matching, and purchase order drafting.</p>
                    </section>

                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Supplier Name</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Metro Office Depot Trading"
                                    value={form.data.supplier_name}
                                    onChange={(event) => form.setData('supplier_name', event.target.value)}
                                    className="w-full rounded border-gray-300 text-sm"
                                    required
                                />
                                <InputError message={form.errors.supplier_name} className="mt-1" />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Contact Person</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Juan Dela Cruz"
                                    value={form.data.contact_person}
                                    onChange={(event) => form.setData('contact_person', event.target.value)}
                                    className="w-full rounded border-gray-300 text-sm"
                                />
                                <InputError message={form.errors.contact_person} className="mt-1" />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Email</label>
                                <input
                                    type="email"
                                    placeholder="supplier@example.com"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    className="w-full rounded border-gray-300 text-sm"
                                />
                                <InputError message={form.errors.email} className="mt-1" />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                                <input
                                    type="text"
                                    placeholder="e.g. 0917-123-4567"
                                    value={form.data.phone}
                                    onChange={(event) => form.setData('phone', event.target.value)}
                                    className="w-full rounded border-gray-300 text-sm"
                                />
                                <InputError message={form.errors.phone} className="mt-1" />
                            </div>

                            <div className="md:col-span-2">
                                <label className="mb-1 block text-sm font-medium text-slate-700">Address</label>
                                <textarea
                                    placeholder="Enter full supplier address"
                                    value={form.data.address}
                                    onChange={(event) => form.setData('address', event.target.value)}
                                    className="w-full rounded border-gray-300 text-sm"
                                    rows={3}
                                />
                                <InputError message={form.errors.address} className="mt-1" />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={(event) => form.setData('status', event.target.value)}
                                    className="w-full rounded border-gray-300 text-sm"
                                >
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                </select>
                                <InputError message={form.errors.status} className="mt-1" />
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <button type="submit" disabled={form.processing} className="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                                {form.processing ? 'Saving...' : 'Save Supplier'}
                            </button>
                            <Link href={route('procurement.suppliers.index')} className="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Return to Suppliers
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
