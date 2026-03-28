import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseRequest = {
    purchase_request_id: number;
    remarks: string | null;
};

type Props = {
    purchaseRequest: PurchaseRequest;
};

export default function PurchaseRequestsEdit({ purchaseRequest }: Props) {
    const form = useForm({
        remarks: purchaseRequest.remarks ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('procurement.purchase-requests.update', purchaseRequest.purchase_request_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Purchase Request</h2>}>
            <Head title="Edit Purchase Request" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-6">
                        <div>
                            <label className="mb-1 block text-sm text-gray-700">Remarks</label>
                            <textarea
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                                className="w-full rounded border-gray-300 text-sm"
                                rows={4}
                            />
                        </div>

                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Update Purchase Request
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
