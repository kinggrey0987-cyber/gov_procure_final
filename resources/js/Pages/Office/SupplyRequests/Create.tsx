import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function SupplyRequestCreate() {
    const form = useForm({
        request_date: new Date().toISOString().slice(0, 10),
        needed_by_date: new Date().toISOString().slice(0, 10),
        request_category: 'GENERAL',
        purpose: '',
        priority_level: 'MEDIUM',
        review_notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('office.supply-requests.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Supply Request</h2>}>
            <Head title="Create Supply Request" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-6">
                        <input type="date" className="w-full rounded border px-3 py-2" value={form.data.request_date} onChange={(e) => form.setData('request_date', e.target.value)} />
                        <input type="date" className="w-full rounded border px-3 py-2" value={form.data.needed_by_date} onChange={(e) => form.setData('needed_by_date', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" value={form.data.request_category} onChange={(e) => form.setData('request_category', e.target.value)} placeholder="Request Category" />
                        <input className="w-full rounded border px-3 py-2" value={form.data.priority_level} onChange={(e) => form.setData('priority_level', e.target.value)} placeholder="Priority Level" />
                        <textarea className="w-full rounded border px-3 py-2" value={form.data.purpose} onChange={(e) => form.setData('purpose', e.target.value)} placeholder="Purpose" />
                        <textarea className="w-full rounded border px-3 py-2" value={form.data.review_notes} onChange={(e) => form.setData('review_notes', e.target.value)} placeholder="Notes" />
                        <button type="submit" className="rounded bg-emerald-600 px-4 py-2 text-white">Save</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
