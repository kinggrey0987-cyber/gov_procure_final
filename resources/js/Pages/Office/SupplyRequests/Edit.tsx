import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Props = {
    supplyRequest: {
        supply_request_id: number;
        request_date: string;
        needed_by_date: string;
        request_category: string;
        purpose: string;
        priority_level: string;
        review_notes: string | null;
    };
};

export default function SupplyRequestEdit({ supplyRequest }: Props) {
    const form = useForm({
        request_date: supplyRequest.request_date,
        needed_by_date: supplyRequest.needed_by_date,
        request_category: supplyRequest.request_category,
        purpose: supplyRequest.purpose,
        priority_level: supplyRequest.priority_level,
        review_notes: supplyRequest.review_notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('office.supply-requests.update', supplyRequest.supply_request_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Supply Request</h2>}>
            <Head title="Edit Supply Request" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-6">
                        <input type="date" className="w-full rounded border px-3 py-2" value={form.data.request_date} onChange={(e) => form.setData('request_date', e.target.value)} />
                        <input type="date" className="w-full rounded border px-3 py-2" value={form.data.needed_by_date} onChange={(e) => form.setData('needed_by_date', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" value={form.data.request_category} onChange={(e) => form.setData('request_category', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" value={form.data.priority_level} onChange={(e) => form.setData('priority_level', e.target.value)} />
                        <textarea className="w-full rounded border px-3 py-2" value={form.data.purpose} onChange={(e) => form.setData('purpose', e.target.value)} />
                        <textarea className="w-full rounded border px-3 py-2" value={form.data.review_notes} onChange={(e) => form.setData('review_notes', e.target.value)} />
                        <button type="submit" className="rounded bg-emerald-600 px-4 py-2 text-white">Update</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
