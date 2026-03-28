import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    supplyRequest: {
        supply_request_id: number;
        request_code: string;
        status: string;
        request_date: string;
        needed_by_date: string;
        request_category: string;
        purpose: string;
        priority_level: string;
        review_notes: string | null;
    };
};

export default function SupplyRequestShow({ supplyRequest }: Props) {
    const submitForm = useForm({ review_notes: supplyRequest.review_notes ?? '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supply Request View</h2>}>
            <Head title="Supply Request View" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-6">
                        <p>Code: {supplyRequest.request_code}</p>
                        <p>Status: {supplyRequest.status}</p>
                        <p>Request Date: {supplyRequest.request_date}</p>
                        <p>Needed By: {supplyRequest.needed_by_date}</p>
                        <p>Category: {supplyRequest.request_category}</p>
                        <p>Priority: {supplyRequest.priority_level}</p>
                        <p>Purpose: {supplyRequest.purpose}</p>
                        <p>Notes: {supplyRequest.review_notes ?? '-'}</p>
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={route('office.supply-requests.edit', supplyRequest.supply_request_id)}
                            className="rounded bg-slate-700 px-4 py-2 text-white"
                        >
                            Edit
                        </Link>
                        <Link
                            href={route('office.supply-requests.items.index', supplyRequest.supply_request_id)}
                            className="rounded bg-emerald-600 px-4 py-2 text-white"
                        >
                            Manage Items
                        </Link>
                        <button
                            onClick={() => submitForm.post(route('office.supply-requests.submit', supplyRequest.supply_request_id))}
                            className="rounded bg-indigo-600 px-4 py-2 text-white"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
